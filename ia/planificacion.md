# Plan de implementación — Sistema de Tickets

> Documento vivo. Cualquier IA o persona que continue el proyecto debe leer este archivo, `ia/requerimientos.md` e `ia/avances.md` antes de modificar código.
>
> Última actualización: 2026-11-19
> Estado: **Fase 3 completada; Fase 4 lista para iniciar**

---

## 1. Propuesta ejecutiva

Construir el sistema en **fases verticales** (cada fase entrega algo funcional y verificable), partiendo de la bandeja de correo hasta llegar al tablero Kanban y al dashboard.

Idea central de diseño: **un ticket es la entrada de trabajo; una tarea raíz es el proyecto; las subtareas son hijas de esa tarea**. Así se unifican "ticket", "proyecto" y "subtareas" en un solo modelo de ejecución, con dependencias, historial de estados, adjuntos y comentarios.

Flujo propuesto:

```
Correo (IMAP) → Mensaje/Hilo → Ticket → Asignación (área/categoría/persona)
     → Tarea raíz (proyecto) → Subtareas (+ dependencias) → Kanban por área y por usuario
     → Transiciones con fecha → Comentarios/adjuntos → Métricas y gráficas (ApexCharts)
```

**Prioridad del MVP:** bandeja → conversión en ticket → asignación con correo de notificación → Kanban con tareas/subtareas → historial y comentarios → dashboard. Todo lo demás (OAuth, SLA avanzados, multi-tenant, móvil) queda como mejora posterior.

---

## 2. Estado real del proyecto (verificado el 2026-11-18)

| Ítem | Situación |
| --- | --- |
| Laravel | 13.43.0 (PHP 8.3) |
| Livewire | 4.4.8 (`livewire/livewire`) + `livewire/flux` 2.21.0 |
| Tailwind | 4.x vía Vite (`vp build`) |
| Frontend | Starter kit Livewire; componentes Livewire **basados en clase** en `app/Livewire/` + vistas en `resources/views/` |
| Auth | Fortify (login, registro, verificación, 2FA, passkeys) ya instalado |
| Base de datos | `.env` usa **MariaDB**; `.env.example` trae SQLite. Las pruebas usan MariaDB `tickets_test` porque el entorno no tiene `pdo_sqlite` |
| Colas | `QUEUE_CONNECTION=database`, tabla `jobs` ya migrada |
| Correo saliente | `MAIL_MAILER=log` (configurar SMTP real cuando se necesite) |
| Permisos Spatie | **Instalado** (`spatie/laravel-permission` 8.5.0), configuración y migración publicadas |
| Cliente IMAP | **Integrado** (`webklex/php-imap` 6.2.0) detrás de `MailClient`; sincronización probada con cliente falso, sin red |
| ApexCharts | **Instalado** (`apexcharts` npm 8.9.1); aún sin integrar |
| Pruebas | Pest 4 + plugin Laravel; Pint, PHPStan/Larastan configurados y en verde (94 tests) |
| Rutas actuales | `/`, `/dashboard`, `/mail/inbox`, `/mail/accounts`, `/attachments/{attachment}`, `/tickets`, `/boards`, `/tasks/mine`, `/settings` |

**Convenciones a respetar:** componentes Livewire de clase (no SFC, aunque Livewire 4 por defecto cree SFC), Layout `x-layouts::app`, rutas en `routes/web.php` / `routes/settings.php`, controladores y modelos con `php artisan make:`, PHP 8.3 con tipos y `curly braces`, Pest para pruebas, Pint al final de cada cambio PHP.

---

## 3. Decisiones

> No instalar dependencias ni cambiar `.env` de producción sin aprobación (regla de `AGENTS.md`). El usuario aprobó las dependencias y el inicio de la Fase 1; las decisiones D4–D10 se están aplicando **por defecto** y deben revisarse antes de implementar cada fase.

| # | Decisión | Resolución / estado | Impacto |
| --- | --- | --- | --- |
| D1 | Dependencias nuevas | ✅ Aprobadas e instaladas: `spatie/laravel-permission`, `webklex/php-imap`, `apexcharts` (npm). No se instaló sanitizador HTML todavía | Permisos, lectura IMAP y gráficas disponibles como base |
| D2 | Motor de BD | ✅ MariaDB para aplicación y pruebas. `phpunit.xml` apunta a `tickets_test` porque el entorno no tiene `pdo_sqlite` | Evita el error `could not find driver`; no se creó sqlite |
| D3 | Autenticación de correo | ✅ Usuario/contraseña cifrados en `mail_accounts.credentials` (`cast: encrypted` + `hidden`) | OAuth2 queda para fase posterior |
| D4 | Proveedor de correo | ⏳ Aceptado por defecto: IMAP + SMTP genéricos | Define campos ya creados en la migración |
| D5 | Organización | ✅ Aplicado: `users.area_id`, `areas.manager_id`, `areas.parent_id` | Base para policies por área |
| D6 | Estados del Kanban | ✅ Aplicado en `TaskStatus`: `todo`, `assigned`, `in_progress`, `testing`, `done`, `blocked`; agrupación en 4 columnas vía `kanbanColumn()` | Falta el tablero visual en Fase 4 |
| D7 | Dependencias entre subtareas | ✅ Aceptado por defecto: solo `finish_to_start` | Motor de validación pendiente en Fase 4 |
| D8 | Adjuntos | ✅ Implementado: disco privado `local`, límite configurable (25 MB por defecto), nombre/extensión saneados y descarga por ruta autorizada. Falta una política MIME/allowlist más estricta y storage remoto en producción | Seguridad y coste |
| D9 | Correo de notificación | ✅ Implementado: evento `TicketAssigned` y `TicketAssignedMail` encolado al remitente; SMTP real pendiente para producción | Con `log` solo se verá en logs |
| D10 | Scope del MVP | ⏳ Aceptado por defecto: una cuenta y un tablero por área | Multicuenta/multiárea se habilita después |

---

## 4. Arquitectura propuesta

### 4.1 Capas

- **Modelos / Eloquent:** entidades y relaciones (`Ticket`, `Task`, `Area`, etc.).
- **Actions** (`app/Actions/...`): casos de uso (`CreateTicketFromMessage`, `AssignTicket`, `MoveTask`, `SyncMailAccount`). Mantienen los Livewire delgados.
- **Jobs / Events / Listeners / Mailable:** sincronización IMAP y notificaciones asíncronas.
- **Policies + Spatie:** autorización centralizada; Livewire y rutas solo llaman `authorize()`.
- **Livewire 4 (clase):** un componente por pantalla; acciones validan y autorizan igual que un controlador.
- **Blade + Flux UI + Tailwind 4:** presentación; `wire:key` en todos los `foreach`, `wire:loading` en acciones lentas.
- **Capa IMAP desacoplada:** interfaz `App\Support\Mail\MailClient` + implementación con `webklex/php-imap`, para poder simularla en pruebas sin conexión real.
- **ApexCharts:** directiva/componente Blade propio con ID único por gráfica e inicialización desde JS de Livewire 4 (`$wire.$js` / hook), con `wire:ignore` donde corresponda.

### 4.2 Estructura de archivos (propuesta)

Se reutilizan carpetas existentes; no se crea una carpeta raíz nueva sin aprobación.

```
app/
  Actions/
    Mail/          # SyncMailAccount, ProcessInboundMessage
    Tickets/       # CreateTicketFromMessage, AssignTicket, NotifyRequester
    Tasks/         # CreateTask, BreakDownTask, MoveTask, ToggleDependency
  Events/          # TicketAssigned, TaskStatusChanged
  Listeners/       # NotifyRequesterTicketAssigned, RecordTaskStatusChange
  Jobs/            # SyncMailAccountJob, SendTicketAssignedMail
  Mail/            # TicketAssignedMail
  Models/          # Area, TicketCategory, MailAccount, MailMessage, Ticket, Task, ...
  Policies/        # TicketPolicy, TaskPolicy, AreaPolicy, CommentPolicy
  Support/Mail/    # MailClient (interface), ImapMailClient, DTOs
  Livewire/        # Mail/, Tickets/, Tasks/, Dashboard/  (convención de clase)
resources/views/
  livewire/        # vistas de esos componentes
  components/charts/  # wrapper ApexCharts
database/
  migrations/ factories/ seeders/
tests/Feature/... tests/Unit/...
```

### 4.3 Reglas técnicas transversales

1. Toda escritura pasa por un Action o por una acción Livewire que valida (FormRequest cuando corresponda) y autoriza (Policy).
2. Todo cambio de estado de una tarea se registra en `task_status_transitions` (fecha, usuario, de/para).
3. Los correos entrantes se deduplican por `(mail_account_id, external_uid)` y por `message_id`.
4. Los archivos se guardan en disco privado; la descarga siempre pasa por una ruta autorizada (nunca URL pública directa).
5. El HTML de correo se sanea o se renders como texto por defecto; nunca se inyecta HTML sin sanitizar.
6. Las credenciales IMAP/SMTP se cifran con `Crypt::encryptString()` y nunca se envían a logs ni al frontend.
7. Cada fase termina con Pint,PHPStan y Pest en verde sobre lo tocado.

---

## 5. Modelo de datos propuesto

> Nombres ya materializados en las migraciones y modelos. Los Enums creados están en `App`: `App\TaskStatus` y `App\TicketPriority`. No fue necesario crear `MailSyncStatus`: `mail_sync_runs` registra `processed`, `failed` y `error`.

| Entidad | Campos clave | Relaciones / notas |
| --- | --- | --- |
| `users` (existente) | + `area_id`, `job_title`, `is_active` | `belongsTo(Area)`, `hasMany(Task, 'assignee_id')`, roles Spatie |
| `areas` | `name`, `slug`, `description`, `manager_id`, `parent_id`, `color`, `is_active` | Auto-relación jerárquica; `manager_id` = jefe de área |
| `ticket_categories` | `name`, `slug`, `color`, `description`, `is_active`, `default_area_id` | Clasificación del ticket para métricas por prioridad/categoría |
| `mail_accounts` | `name`, `email`, `imap_host/port/encryption`, `smtp_host/port/encryption`, `username`, `credentials` (cifrado), `is_active`, `last_synced_at`, `sync_every_minutes` | Una cuenta por MVP; `owner_user_id` opcional |
| `mail_messages` | `mail_account_id`, `folder`, `external_uid`, `message_id`, `thread_key`, `parent_message_id`, `subject`, `from_name`, `from_email`, `to`, `cc`, `body_text`, `body_html`, `received_at`, `is_read` | Único por `(account, folder, uid)`; `thread_key` agrupa hilos por `References`/`In-Reply-To`/asunto normalizado |
| `attachments` | `attachable_type`, `attachable_id`, `disk`, `path`, `original_name`, `mime_type`, `size_bytes`, `checksum`, `source` (`email`/`upload`), `uploaded_by` | Polimórfico: mensajes, tickets, tareas y comentarios comparten tabla |
| `tickets` | `code` (TKT-0001), `source` (`email`/`manual`), `mail_message_id`, `subject`, `description`, `requester_name`, `requester_email`, `area_id`, `category_id`, `priority`, `status`, `assignee_id`, `created_by`, `assigned_at`, `closed_at`, `due_at` | `hasOne` tarea raíz; índices en `status`, `area_id`, `category_id`, `priority`, `assignee_id` |
| `tasks` | `ticket_id`, `parent_id`, `title`, `description` (longText), `assignee_id`, `area_id`, `status`, `priority`, `estimated_minutes`, `actual_minutes`, `due_at`, `started_at`, `testing_at`, `completed_at`, `position` | Auto-relación; `parent_id = null` ⇒ tarea raíz/proyecto; `position` para ordenar columnas Kanban |
| `task_dependencies` | `task_id`, `depends_on_task_id`, `type` | Único por par; solo `finish_to_start` en MVP |
| `task_status_transitions` | `task_id`, `from_status`, `to_status`, `changed_by`, `occurred_at`, `note` | Trazabilidad de fechas por estado; índice `(task_id, occurred_at)` |
| `comments` | `commentable_type`, `commentable_id`, `user_id`, `body`, `is_internal` | Polimórfico (ticket/tarea); `is_internal` visible solo para jefes/designados |
| `mail_sync_runs` | `mail_account_id`, `started_at`, `finished_at`, `processed`, `failed`, `error` | Auditoría de sincronización |
| `notifications` | Estándar Laravel | Notificaciones in-app opcionales (además del correo) |

**Índices obligatorios:** `tickets(status, area_id)`, `tickets(category_id, priority)`, `tasks(ticket_id, parent_id)`, `tasks(assignee_id, status)`, `mail_messages(mail_account_id, received_at)`, `task_status_transitions(task_id, occurred_at)`.

### 5.1 Matriz de estados sugerida

| Estado | Significado | Columna Kanban |
| --- | --- | --- |
| `todo` | Ticket/tarea creada, sin asignar | Tareas |
| `asignado` | Ya tiene responsable, no iniciada | Asignado |
| `en_proceso` | Trabajándose | En proceso |
| `pruebas` | Terminada, en validación (foco TI) | En proceso (o columna propia si se aprueba 5ª) |
| `terminado` | Cerrada | Terminado |
| `bloqueado` | Dependencia externa / espera | En proceso (badge) |

---

## 6. Roles y permisos (Spatie)

| Rol | Permisos clave | Alcance |
| --- | --- | --- |
| `super_admin` | Todos | Global |
| `mail_manager` | `mail.accounts.*`, `tickets.viewAny`, `tickets.assign` | Bandeja y asignación |
| `jefe_area` | `areas.viewOwn`, `areas.manageOwn`, `tasks.*`, `tickets.viewOwnArea`, `comments.viewInternal`, `reports.viewOwnArea` | Solo su área y sus subordinados |
| `colaborador` | `tasks.viewAssigned`, `tasks.updateOwn`, `comments.create`, `attachments.upload` | Solo tareas asignadas |
| `consultor` (opcional) | `reports.view` | Solo lectura de dashboard |

**Policies:** `TicketPolicy` (ver/asignar según área), `TaskPolicy` (crear/desglosar/mover solo si es jefe del área o responsable de la tarea; respetar dependencias), `AreaPolicy` (un jefe no administra otras áreas), `CommentPolicy` (ver internos solo jefes/designados), `AttachmentPolicy` (descargar solo si puede ver el recurso padre).

---

## 7. Fases y criterios de aceptación

### Fase 0 — Confirmaciones
- [x] Responder D1–D10 de la sección 3 (D4–D10 quedan como valores por defecto revisables).
- [x] Aprobar creación de subcarpetas bajo `app/` y dependencias nuevas.
- [ ] Confirmar credenciales IMAP/SMTP de prueba (sin poner secretos en el repo). **Pendiente para la conexión real; la Fase 2 se validó con cliente falso.**
- **Salida:** decisiones registradas en `ia/avances.md` y plan ajustado. ✅

### Fase 1 — Cimientos (M) ✅
- [x] Instalar dependencias aprobadas y publicar configuración/migraciones de Spatie.
- [x] Crear Enums (`TaskStatus`, `TicketPriority`), 13 migraciones de dominio, modelos, factories y seeders.
- [x] Agregar `area_id`, `job_title`, `is_active` a `users` y relaciones con `areas`/`tasks`.
- [x] Crear Policies; Laravel 13 las descubre automáticamente por convención, sin registro manual.
- [x] Reemplazar dashboard de ejemplo por componentes Livewire de clase y navegación Flux condicionada por permisos.
- [x] Tests: seeders/roles, policies base, navegación por rol, dashboard.
- **Aceptación:** 60 tests Pest en verde, PHPStan sin errores y Pint aplicado. No se ejecutó `migrate:fresh` contra la BD local para no borrar datos; la cobertura equivalente se valida con `RefreshDatabase` sobre `tickets_test`.

### Fase 2 — Cliente de correo (L) ✅
- [x] UI para alta/edición de `mail_accounts` (cifrado de credenciales, prueba de conexión y sincronización manual).
- [x] `MailClient` interface + `ImapMailClient` con `webklex/php-imap`, enlazados en `AppServiceProvider`.
- [x] Job encolado `SyncMailAccountJob` + comando Artisan `tickets:sync-mail` (`--account`, `--due`) + scheduler en `routes/console.php`.
- [x] Persistencia de mensajes/hilos, deduplicación por UID/`message_id`, adjuntos en disco privado y registro de `mail_sync_runs`.
- [x] Vista Bandeja: lista paginada de mensajes, lectura y marcado como leído, hilos, filtros por cuenta/búsqueda/no leídos, cuerpo en texto plano y descarga segura de adjuntos.
- [x] Tests: sincronización con cliente falso (sin red), deduplicación, hilos, límite de adjuntos, errores, cifrado, UI, navegación y descarga autorizada con `Storage::fake`.
- **Aceptación:** al ejecutar el job se importan correos y adjuntos sin duplicados; la bandeja se ve en Livewire con Flux. Verificado con 81 tests en verde. **Pendiente para operación real:** credenciales IMAP/SMTP y prueba contra un buzón real.

### Fase 3 — De correo a ticket y notificación (M) ✅
- [x] Action `CreateTicketFromMessage` (desde bandeja) con código `TKT-0001` secuencial y deduplicación por mensaje.
- [x] Action `AssignTicket` (área, categoría, prioridad, responsable) con Policy y validación de pertenencia del responsable al área.
- [x] Evento `TicketAssigned` → listener `NotifyRequesterTicketAssigned` y `TicketAssignedMail` encolado al remitente.
- [x] Creación automática de la tarea raíz (proyecto) al asignar a un área; reutilizada en re-asignaciones.
- [x] Historial inicial en `task_status_transitions` y marca de tiempo `assigned_at`.
- [x] UI: conversión desde la bandeja y listado/asignación de tickets en Livewire + Flux.
- [x] Tests: asignación autorizada/no autorizada, correo con `Mail::fake`, tarea raíz creada, re-asignación y UI.
- **Aceptación:** asignar un ticket dispara correo al remitente y crea la tarea raíz en el área. Verificado con 94 tests en verde. **Pendiente para operación real:** SMTP real y conexión a buzón con la cuenta configurada en la UI.

### Fase 4 — Kanban y gestión de proyectos (L)
- [x] Componente Livewire del tablero con DnD (`wire:sort` de Livewire 4), resuelto como tablero único de 3 columnas con scopes `Mis tareas | Mi área | Todas`.
- [x] Action `MoveTask`: valida transición permitida, autoriza, actualiza `position` y registra `task_status_transitions`.
- [x] Creación y desglose de subtareas desde el tablero, con título y descripción larga.
- [x] Asignación directa a colaborador del área (sin crear subtareas) y a cada subtarea, con reasignación integrada en `Editar tarea`.
- [x] Tiempos opcionales: estimado, real, fecha límite; validación numérica.
- [x] Dependencias `finish_to_start`: bloquear inicio si la dependencia no está terminada.
- [x] Vista detalle de tarea/proyecto: descripción, subtareas, dependencias, adjuntos, comentarios, historial.
- [x] Tests: DnD por Livewire, transiciones válidas/inválidas, dependencias, permisos de jefe, QUIÉN puede desglosar.
- **Aceptación:** el jefe convierte ticket en proyecto con subtareas dependientes; arrastrar cambia estado y fecha; un colaborador ajeno no puede mover tareas. Verificado con la suite completa en verde.

### Fase 5 — Flujo del colaborador y trazabilidad (M)
- [x] Página "Mis tareas" integrada como scope del tablero unificado filtrado por `assignee_id`.
- [x] Cambio a `en_proceso` y a `pruebas` con validación; evidencia opcional antes de `terminado`.
- [x] Comentarios con texto, enlaces, capturas/fotos/archivos; `is_internal` para feedback del superior.
- [x] Visibilidad de comentarios internos para jefe/designado; notificación al superior cuando hay cambios.
- [x] Descarga segura de adjuntos por ruta autorizada.
- [x] Tests: comentario interno vs público, subida con MIME inválido, visibilidad del superior, historial completo.
- **Aceptación:** colaborador mueve sus tareas y deja evidencia; jefe ve feedback y fechas de cada etapa. Verificado con la suite completa en verde.

### Fase 6 — Dashboard y gráficas (M)
- [x] Queries agregadas (optimizadas e indexadas) para KPIs: totales por estado, por área, por prioridad/categoría, % completados, tiempo promedio por etapa, tickets recientes, tendencia semanal, carga por colaborador, cumplimiento de plazos.
- [x] Componente ApexCharts reutilizable (IDs únicos, `wire:ignore`, actualización vía Livewire).
- [x] Vista Dashboard con filtros (rango de fechas, área, categoría, responsable) y permisos por rol.
- [x] Tests: métricas con datos sembrados, filtros, acceso restringido.
- **Aceptación:** gráficas renderizan sin errores JS y los KPIs coinciden con datos sembrados. Verificado con suite completa en verde y browser logs sin errores tras la recarga.

### Fase 7 — Hardening y entrega (S–M)
- [ ] Configurar SMTP real, `storage:link`, queue worker y scheduler en producción.
- [ ] Rate limiting en sincronización y subidas; manejo de errores y logs con contexto.
- [ ] Revisión de N+1, Pint, PHPStan, Pest completo.
- [ ] Seeders de demostración y datos de prueba coherentes.
- [ ] Registrar en `ia/avances.md` el estado final y próximas mejoras (OAuth2, SLA, móvil, S3, multiárea).
- **Aceptación:** `composer test` (o comandos equivalentes) en verde y flujo completo probado de extremo a extremo.

---

## 8. Estrategia de pruebas

- **Feature tests (Pest)** para cada acción relevante: sincronización, asignación+correo, transiciones, dependencias, comentarios/adjuntos y dashboard.
- **Livewire tests** para interacción de bandeja, tablero y formularios (`Livewire::test(...)`).
- **Dobles:** `Mail::fake()`, `Storage::fake()`, `Queue::fake()`, cliente IMAP simulado mediante la interfaz; nunca tests que dependan de red real.
- **Datos:** factories para todos los modelos nuevos; estados de factory para `user jefe`, `colaborador`, `ticket sin asignar`, `tarea terminada`.
- **Casos de fallo obligatorios:** acceso cruzado de área, transición inválida, dependencia incumplida, mensaje duplicado, MIME/size de adjunto inválido, HTML malicioso en correo, correo de notificación fallido (reintento de cola).
- Ejecutar siempre el subconjunto afectado: `php artisan test --compact <ruta>`, y al final Pint + PHPStan.

---

## 9. Seguridad y operación

- Credenciales cifradas en BD; secretos solo en `.env` y nunca en `ia/*.md`.
- Adjuntos en disco privado + rutas con `authorize()`; validar extensión **y** MIME; límite de tamaño.
- Sanitizar HTML entrante; por defecto mostrar texto plano del correo.
- Verificación de permisos en Policies y en cada acción Livewire (no solo ocultar botones).
- Jobs idempotentes (deduplicación) y con reintentos; `withoutOverlapping()` en el scheduler.
- Logs estructurados sin datos sensibles; `MAIL_MAILER=log` solo en desarrollo.
- Backups de BD y storage antes de migraciones destructivas.

---

## 10. Cómo continuar (checklist para la próxima IA)

1. Leer `ia/requerimientos.md`, este archivo y **la última entrada** de `ia/avances.md`.
2. Verificar estado real: `php artisan --version`, `php artisan migrate:status`, `php artisan route:list --except-vendor`, `git status` (si aplica).
3. Confirmar que las decisiones D1–D10 siguen vigentes; si cambiaron, actualizar este plan **antes** de escribir código.
4. Tomar la primera fase con checkboxes sin marcar; marcar solo lo que realmente quedó implementado y probado.
5. Respetar convenciones (Livewire de clase, Actions, Policies, Pest, Pint). No crear documentación adicional salvo que se pida.
6. Al terminar la sesión, agregar una entrada nueva a `ia/avances.md` con: fecha, fase, lo hecho, problemas, decisiones, próximo paso y comandos ejecutados.
7. No instalar dependencias ni modificar `.env` de producción sin aprobación explícita.

---

## 11. Mejoras futuras (fuera del MVP)

OAuth2 para correo, SLA con escalado y pausas, plantillas de subtareas por categoría, tablero por múltiples áreas y filtros globales, notificaciones push, almacenamiento S3, API REST versionada, modo móvil PWA, auditoría completa con `spatie/laravel-activitylog`, reportes exportables a Excel/PDF.
