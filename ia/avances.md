# Registro de avances — Sistema de Tickets

> Bitácora para que cualquier IA pueda retomar el trabajo. **La entrada más reciente va arriba.**
>
> Formato de cada entrada: fecha, fase/estado, qué se hizo, hallazgos, decisiones, próximo paso y comandos ejecutados.

---

## Estado general

| Fase | Estado | Observaciones |
| --- | --- | --- |
| 0 — Confirmaciones | ✅ Completada | D1–D10 resueltas o aceptadas por defecto; credenciales IMAP/SMTP reales siguen pendientes para prueba de buzón |
| 1 — Cimientos | ✅ Completada | Dependencias, migraciones, modelos, factories, seeders, policies, navegación y pruebas |
| 2 — Cliente de correo | ✅ Completada | IMAP desacoplado, sincronización, hilos, adjuntos privados y bandeja funcional |
| 3 — Ticket y notificación | ✅ Completada | Conversión desde bandeja, asignación, tarea raíz y correo encolado al remitente |
| 4 — Kanban/proyectos | ✅ Completada | Tablero único de 3 columnas, scopes, DnD, subtareas, dependencias, tiempos y vista detalle |
| 5 — Colaborador/trazabilidad | ✅ Completada | Mis tareas, comentarios/adjuntos privados, evidencia antes de Done y notificación al superior |
| 6 — Dashboard | ✅ Completada | KPIs agregados/indexados, gráficas ApexCharts, filtros y permisos por rol; tabla única de entregables con regla “raíz manda” y subtareas solo al expandir |
| 7 — Hardening | ⏳ Pendiente | Colas, scheduler, seguridad, pruebas finales |

---

## 2026-11-21 — Avatar y fecha de estado en las subtareas del dashboard ✅

- **Fase:** mejora de apariencia del dashboard (Fase 6, refinamiento).
- **Qué se hizo:**
    - En las subtareas desplegadas se sustituyó el texto del responsable por un `flux:avatar` circular tamaño `xs`, con color automático sembrado por `assignee_id`; el **nombre del responsable queda solo en el tooltip** (arriba). Para subtareas sin responsable se mantiene un avatar genérico con tooltip `Sin responsable`.
    - La fecha situada después del badge de estado ahora es la **fecha del último cambio de estado** (`latestStatusTransition.occurred_at`), con respaldo a `completed_at` y luego `updated_at` para tareas sin transiciones registradas. Sigue ubicada inmediatamente después del badge.
    - `Dashboard\Index::expandedSubtasks()` precarga `assignee` y `latestStatusTransition` para evitar consultas N+1; `Dashboard\Index::subtaskStatusDateTime()` centraliza el formato `d/m/Y H:i`.
- **Hallazgos:**
    - El commit previo `a2fe2e7` (-formateo y reajuste de celdas-) dejó las celdas sin `text-center`; se respetó ese estado y no se revirtieron esos cambios.
    - El nombre del responsable también aparece en el selector de filtros, por lo que la prueba verifica además los marcadores `data-flux-avatar` y `data-flux-tooltip-content` para confirmar que el tooltip existe.
- **Decisiones:**
    - Se interpreta “fecha del estado” como la fecha en que la subtarea entró en su estado actual, no como la fecha de terminación; por eso se usa la última transición y se conserva `completed_at` solo como respaldo.
    - No se agregaron dependencias ni permisos. Solo cambiaron la vista, el componente Livewire y una prueba feature.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo. Publicar los commits pendientes cuando haya credenciales/SSH disponibles.
- **Comandos ejecutados:**
    - `vendor/bin/pint --dirty --format agent` → aplicado.
    - `php artisan view:clear` → vistas compiladas limpias.
    - `php artisan test --compact tests/Feature/DashboardReportTest.php` → **16/16 en verde, 118 aserciones**.
    - `php artisan test --compact` → **193/193 en verde, 644 aserciones**.
    - `vendor/bin/phpstan analyse --no-progress --error-format=raw` → **0 errores**.

---

## 2026-11-21 — Ajuste visual de la tabla de entregables recientes ✅

- **Fase:** mejora de apariencia del dashboard (Fase 6, refinamiento).
- **Qué se hizo:**
    - Centrado horizontal de todas las columnas de `Entregables recientes` (encabezados y celdas): entregable, progreso, área/prioridad y acciones. El progreso usa `justify-center` y el contenido del área se apila con `items-center`.
    - Al desplegar una fila con subtareas, el panel usa un fondo/borde del **mismo color del progreso** de la tarea (rojo, naranja, amarillo o verde) y las filas de subtareas un tono más claro de esa misma familia, de modo que la pertenencia se reconoce por color.
    - Se eliminó el texto `Subtarea de: <título>` y el encabezado `Subtareas de <título>`; ahora el encabezado dice `Subtareas · <progreso>` y cada fila muestra directamente **el título de la subtarea** (con `title` para tooltips), responsable, estado y fecha de terminación.
    - El mapeo de colores se movió a `Dashboard\Index::subtaskPanelClasses()` para mantener la vista simple y evitar lógica de presentación repetida.
- **Hallazgos:**
    - Blade enmascara los bloques `@php … @endphp` como PHP crudo; al existir ya `@php($kpis = …)` en la misma vista, el bloque nuevo absorbía el resto de directivas (`@if`, `@foreach`) y provocaba `syntax error, unexpected token "endif"` al compilar. Se sustituyó por un `@php(...)` de una línea que llama al método del componente.
    - La prueba `it renders a collaborator subtask with its parent in the dashboard table` se actualizó: ahora verifica que **no** aparezca `Subtarea de: …` y que sí se vea el título de la subtarea.
- **Decisiones:**
    - El color del panel sigue la barra de progreso de `DashboardWorkItem::$progressColor`; no se introdujeron colores nuevos ni clases arbitrarias.
    - No se agregaron dependencias ni permisos; solo cambiaron la vista, el componente Livewire y una aserción de prueba.
    - El título de la subtarea se muestra como texto principal; el tooltip conserva el nombre completo si se trunca.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo. Publicar los commits pendientes cuando haya credenciales/SSH disponibles.
- **Comandos ejecutados:**
    - `vendor/bin/pint --dirty --format agent` → aplicado.
    - `php artisan view:clear` → vistas compiladas limpias.
    - `php artisan test --compact tests/Feature/DashboardReportTest.php` → **16/16 en verde, 114 aserciones**.
    - `php artisan test --compact` → **193/193 en verde, 640 aserciones**.
    - `vendor/bin/phpstan analyse --no-progress --error-format=raw` → **0 errores**.

---

## 2026-11-21 — Consolidación de avances y publicación en `origin/main` ✅

- **Fase:** cierre de jornada. Fases 0–6 completadas y publicadas; Fase 7 sigue pendiente.
- **Qué se hizo:**
    - **Fase 0–1:** confirmadas D1–D10 e instaladas las dependencias autorizadas (`spatie/laravel-permission`, `webklex/php-imap`, `apexcharts`). Creadas 13 migraciones de dominio, modelos, factories, seeders, policies, navegación por permisos y componentes Livewire base. Suite en **60/60**.
    - **Fase 2:** cliente IMAP desacoplado, sincronización con deduplicación/hilos/adjuntos privados, job con reintentos, comando `tickets:sync-mail`, scheduler y bandeja funcional. Suite en **81/81**.
    - **Fase 3:** conversión de mensajes a ticket con código `TKT-0001`, asignación con validación de área, tarea raíz, evento/listener y Mailable encolado. Suite en **94/94**.
    - **Fases 4–5:** Kanban único de 3 columnas, DnD, subtareas, dependencias, tiempos, vista detalle, comentarios/adjuntos privados, administración de roles y altas de colaborador en su área. Optimización con lotes 50/20, historial bajo demanda, índices y vista Lista. Suite en **172/172**.
    - **Fase 6:** dashboard con KPIs agregados e indexados, gráficas ApexCharts, filtros por rol y rango de fechas; regla **raíz manda** para separar entregables de subtareas; cierre de ticket al terminar la raíz; tabla única `Entregables recientes` con progreso, expansión de subtareas, dropdown y modal; deep link `Ver ticket` con `?search=<código>`. Suite en **193/193**.
    - **Publicación:** los commits funcionales `0fadce2` y `043cc4e` están en `origin/main`. El commit que actualiza esta bitácora queda local y pendiente de publicación por falta de credenciales HTTPS.
- **Hallazgos:**
    - El primer intento de `git push` falló por falta de credenciales HTTPS; en esta jornada el push se completó y `origin/main` ya apunta a `043cc4e`.
    - Las entradas anteriores todavía indicaban publicación pendiente; se actualizan para reflejar el estado real.
    - Los ajustes finales de tipado estático exigieron documentar relaciones nullable en `Task`/`Ticket` y extraer los `?->` bajo `??` a variables locales para evitar `nullsafe.neverNull`, sin ignores ni baseline.
- **Decisiones:**
    - La raíz sigue siendo la unidad de entrega: las subtareas solo muestran avance y nunca cierran por sí solas.
    - `Listas para revisión` es un filtro dentro de `Entregables recientes`, no una tabla ni tarjeta separada.
    - No se crearon permisos nuevos: se reutilizan `reports.view`, `reports.viewOwnArea`, `tickets.viewAny` y la gestión de área.
    - Correo entrante solo IMAP; SMTP continúa en modo `log` hasta la Fase 7. Nunca se almacenaron credenciales reales en código.
    - `Ver ticket` usa `#[Url]` en `Tickets\Index.search` para hidratar el filtro desde la query string.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo. Publicar el commit de esta bitácora cuando haya credenciales/SSH disponibles.
- **Comandos ejecutados:**
    - `php artisan test --compact` → **193/193 en verde, 640 aserciones**.
    - `php artisan test --compact tests/Feature/DashboardReportTest.php tests/Feature/TaskCompletionRulesTest.php tests/Feature/Tickets/TicketsUiTest.php` → **26/26 en verde, 143 aserciones**.
    - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
    - `vendor/bin/pint --dirty --format agent` → aplicado.
    - `npm run build` → correcto.
    - `git status -sb` / `git rev-parse HEAD origin/main` → `origin/main` en `043cc4e`; el commit de bitácora queda local, 1 commit adelante.
    - `browser-logs` → sin errores JS nuevos en `/dashboard` ni `/boards`.
- **Checklist del plan actualizado:** sí; Fases 0–6 marcadas como completadas y publicadas.

---

## 2026-11-20 — Fase 6: tabla única de entregables recientes en el dashboard ✅

- **Fase:** 6 (Dashboard), unificación final de entregables, listas para revisión y tickets recientes.
- **Qué se hizo:**
    - Creado `App\Actions\Reports\DashboardWorkItem`: DTO único de cada fila, con clave estable `task-{id}`/`ticket-{id}`, título `TKT-0004 — Asunto`, progreso, colores, fechas y actividad ordenable.
    - Refactorizado `BuildDashboardReport::handle()` para publicar `recentWorkItems` y `readyForReviewWorkItems`, deduplicando por clave y precargando relaciones. La tarea raíz siempre gana sobre el ticket cuando ambos existen; los tickets sin tarea aparecen como fila independiente con `Sin asignar` cuando corresponde.
    - Reemplazadas las tablas separadas por una sola tabla `Entregables recientes` en `resources/views/livewire/dashboard/index.blade.php`, con filtro `Listas para revisión`, barra de progreso, colores rojo/naranja/amarillo/verde y conteo `x/y subtareas`.
    - La expansión bajo demanda muestra las subtareas con `Subtarea de: <título>`, responsable, estado y fecha de terminación. El dropdown de tres puntos ofrece `Detalles` y `Ver ticket` solo cuando hay ticket; el modal muestra código, asunto, área, categoría, prioridad, responsable, solicitante/correo, fechas y descripción.
    - `Dashboard\Index` añadió `workItems()`, `expandedWorkItemId`, `detailsWorkItemKey`, `toggleWorkItem()`, `openDetails()` y validación de claves contra el reporte del usuario (`#[Locked]`).
    - `Ver ticket` ahora enlaza a `tickets.index` con `?search=<código>`. Se añadió `#[Url]` a la propiedad `search` de `App\Livewire\Tickets\Index` para que el deep link filtre realmente la lista.
    - Anotadas relaciones nullable en `Task` y `Ticket` para que Larastan infiera correctamente los fallbacks del DTO; los `?->` bajo `??` se extrajeron a variables locales para cumplir la regla `nullsafe.neverNull`.
- **Hallazgos:**
    - La implementación intermedia todavía pintaba el progreso con `amber` en `Listas para revisión`; se corrigió a verde solo cuando `Done` sin subtareas o cuando el desglose llega al 100 %.
    - `statusChangedByName` caía al creador de la tarea/ticket aunque no hubiera transición registrada; ahora solo se muestra si existe transición real.
    - `route('tickets.index', ['search' => ...])` no hidrataba `search` porque la propiedad no tenía `#[Url]`; el enlace parecía correcto pero no filtraba.
    - PHPStan 2.3 marca `?->prop ?? …` como `nullsafe.neverNull` incluso con intermedios nullable; la corrección sin ignorar el error fue evaluar la ruta en una variable local antes del coalesce.
- **Decisiones:**
    - Una sola tabla: `Listas para revisión` es un filtro/estado dentro de `Entregables recientes`, no una tarjeta ni tabla separada.
    - Sin subtareas: barra verde `100 %` únicamente en `Terminado`; en los demás estados manda el badge y se muestra `Sin desglose`.
    - Con subtareas: rojo `0–24 %`, naranja `25–49 %`, amarillo `50–99 %`, verde `100 %`; el porcentaje nunca cierra la raíz por sí solo.
    - El modal es solo lectura y se resuelve contra el reporte autorizado del usuario, sin consultas nuevas por clave arbitraria.
    - No se crearon permisos nuevos ni dependencias nuevas.
- **Archivos creados/modificados (resumen):**
    - Nuevo: `app/Actions/Reports/DashboardWorkItem.php`.
    - Modificados: `app/Actions/Reports/BuildDashboardReport.php`, `app/Livewire/Dashboard/Index.php`, `resources/views/livewire/dashboard/index.blade.php`, `app/Livewire/Tickets/Index.php`, `app/Models/Task.php`, `app/Models/Ticket.php`, `tests/Feature/DashboardReportTest.php`, `tests/Feature/Tickets/TicketsUiTest.php`, `ia/avances.md`.
- **Pruebas ejecutadas:**
    - `php artisan test --compact tests/Feature/DashboardReportTest.php tests/Feature/TaskCompletionRulesTest.php tests/Feature/Tickets/TicketsUiTest.php` → **26/26 en verde, 143 aserciones**.
    - `php artisan test --compact` → **193/193 en verde, 640 aserciones**.
    - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
    - `vendor/bin/pint --dirty --format agent` → aplicado.
    - `npm run build` → correcto; solo advertencias conocidas de `fontaine` opcional y tamaño del bundle.
    - `browser-logs` → sin errores JS nuevos en `/dashboard` ni `/boards`.
- **Problemas / bloqueos resueltos:**
    - Fallo inicial de eager load: los closures de `with()` reciben `Builder|Relation`; se ajustaron los tipos y la importación de `Relation`.
    - La prueba XSS fallaba porque el `<script>` estaba en el asunto del ticket, pero la fila usa el título de la tarea raíz; se movió el payload al lugar correcto.
    - Se mantuvo `Ver ticket` con deep link funcional en lugar de dejar solo el enlace genérico al listado.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo. Publicación resuelta: ambos commits están en `origin/main` (detalle en la entrada del 2026-11-21).
- **Checklist del plan actualizado:** sí, Fase 6 queda documentada con la tabla unificada y el deep link de tickets.

---

## 2026-11-20 — Fase 6: ajuste “raíz manda” en dashboard y cierre ✅

- **Fase:** 6 (Dashboard), ajuste funcional derivado de la revisión de KPIs con el usuario.
- **Qué se hizo:**
    - Separado el reporte en **entregables** (`tasks.parent_id IS NULL`) y **subtareas** (`tasks.parent_id IS NOT NULL`). Los KPIs de productividad ahora cuentan solo entregables; las subtareas reportan avance operativo.
    - Renovados los KPIs: `Entregables`, `Entregables terminados`, `% entregables terminados`, `Subtareas terminadas`, `Avance del desglose`, `Listas para revisión` y `Vencidas`. Las gráficas de estado, área, tendencia y tiempos por etapa usan entregables; prioridad y categoría siguen usando tickets.
    - La carga por colaborador ahora muestra **activas y terminadas**, de modo que quien entrega no desaparece de la gráfica.
    - Añadidas las tablas `Tareas recientes` (con badge `Tarea`/`Subtarea`, `Subtarea de: …`, responsable, estado y fecha de terminación) y `Listas para revisión` (raíces con todas sus subtareas terminadas y aún no aprobadas).
    - Agregados los scopes `roots()`, `subtasks()` y `readyForReview()` en `App\Models\Task` para centralizar las reglas de nivel de tarea.
    - `MoveTask` ahora impide aprobar una raíz con subtareas pendientes, exige el ability `approve` para la aprobación final de raíces desglosadas y no cambia el estado de la raíz al terminar la última subtarea.
    - Creado `App\Actions\Tickets\CloseTicketWhenRootDone`: cuando una raíz llega a `Terminado`, cierra el ticket asociado y fija `closed_at`. Las tareas raíz sin subtareas pueden ser terminadas directamente por su responsable, manteniendo el flujo simple.
    - `TaskPolicy::approve()` reutiliza permisos existentes: `super_admin`, `tickets.viewAny` o jefe que gestione el área. No se crearon permisos nuevos.
    - Actualizado `tests/Feature/DashboardReportTest.php` y creado `tests/Feature/TaskCompletionRulesTest.php` con los casos de colaborador, desglose parcial, aprobación final, cierre de ticket y asignación directa.
- **Hallazgos:**
    - El dashboard anterior mezclaba raíces y subtareas: una sola subtarea terminada podía mostrar `100 % completadas` mientras la raíz seguía en `Pruebas` y el ticket en `Assigned`.
    - `Tickets recientes` solo consulta `tickets`, por eso el trabajo terminado de un colaborador asignado a subtareas no aparecía ahí.
    - No existía ninguna acción que sincronizara `tickets.status` ni `tickets.closed_at` al terminar la tarea raíz.
- **Decisiones:**
    - Regla acordada con el usuario: **raíz manda**. La raíz es la unidad de entrega; las subtareas son seguimiento y nunca cierran la entrega por sí solas.
    - Terminar todas las subtareas deja la raíz en `Listas para revisión`; el jefe/permiso global hace el pase final a `Terminado`.
    - Una raíz sin subtareas sí puede ser finalizada por el colaborador asignado, para no penalizar el trabajo simple.
    - La aprobación final de raíces desglosadas no usa permisos nuevos: reutiliza `tickets.viewAny` y la gestión del área.
    - El alcance por `area_id`/`assignee_id` y el filtro por fecha de creación se mantienen sin cambios.
- **Archivos creados/modificados (resumen):**
    - Nuevos: `app/Actions/Tickets/CloseTicketWhenRootDone.php`, `tests/Feature/TaskCompletionRulesTest.php`.
    - Modificados: `app/Actions/Reports/BuildDashboardReport.php`, `app/Models/Task.php`, `app/Actions/Tasks/MoveTask.php`, `app/Policies/TaskPolicy.php`, `app/Livewire/Dashboard/Index.php`, `resources/views/livewire/dashboard/index.blade.php`, `tests/Feature/DashboardReportTest.php`, `ia/avances.md`.
- **Pruebas ejecutadas:**
    - `php artisan test --compact tests/Feature/DashboardReportTest.php tests/Feature/TaskCompletionRulesTest.php tests/Feature/MoveTaskTest.php tests/Feature/CreateTaskTest.php` → **26/26 en verde, 104 aserciones**.
    - `php artisan test --compact` → **185/185 en verde, 581 aserciones**.
    - `vendor/bin/phpstan analyse --no-progress` → **0 errores** tras corregir los genéricos de `Collection` en la carga por colaborador.
    - `vendor/bin/pint --dirty --format agent` → aplicado.
    - `npm run build` → correcto; solo advertencias conocidas de tamaño del bundle y `fontaine` opcional.
- **Problemas / bloqueos resueltos:**
    - PHPStan rechazó `Collection<int|string|null, …>` porque las claves de una colección no pueden ser `null`. Los valores `NULL` de `assignee_id` llegan como clave `''`; se ajustaron las anotaciones y comparaciones a `int|string` con centinela `''`.
    - Existía una aparente contradicción entre “solo el jefe aprueba” y “el colaborador puede terminar la tarea asignada directamente”: se resolvió exigiendo aprobación únicamente cuando la raíz tiene subtareas.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo. La publicación ya se completó; `origin/main` apunta a `043cc4e`.
- **Checklist del plan actualizado:** sí, Fase 6 queda documentada con la regla “raíz manda”.

---

## 2026-11-20 — Fase 6: Dashboard y gráficas ✅

- **Fase:** 6 (Dashboard y gráficas) completada e integrada con el tablero unificado.
- **Qué se hizo:**
   - Creado `App\Actions\Reports\BuildDashboardReport`: una sola capa de agregación para KPIs de tickets y tareas, con alcance por rol (`super_admin`/`reports.view` → todas las áreas activas, `reports.viewOwnArea` → áreas gestionadas, colaborador → solo sus datos) y filtros validados de fecha, área, categoría y responsable.
   - Calculados totales de tickets, abiertos, tareas, terminadas, `% completadas`, vencidas, ciclo promedio, distribución por estado/área/prioridad/categoría, tendencia semanal, carga por colaborador, tiempos por etapa y tickets recientes con relaciones eager-loadadas.
   - Reemplazado `app/Livewire/Dashboard/Index.php` por un componente con propiedades computadas cacheadas, invalidación explícita al cambiar filtros y `clearFilters()`.
   - Rehecha `resources/views/livewire/dashboard/index.blade.php` con KPIs, tabla de tickets recientes y seis gráficas ApexCharts; el dashboard sigue accesible para cualquier usuario autenticado pero los datos se limitan por permisos.
   - Creado el componente reutilizable `resources/views/components/apex-chart.blade.php` con `wire:ignore`, `wire:key` único por combinación de filtros, `ApexCharts` global expuesto desde `resources/js/app.js` y destrucción al reemplazarse.
   - Añadida la migración `add_dashboard_report_indexes_to_tasks_and_tickets_tables` con índices `(area_id, created_at)` y `(created_at)` en `tasks` y `tickets`.
   - Extendido `tests/Feature/DashboardReportTest.php` con métricas sembradas, alcance por rol, filtros combinados, render Livewire, escape de HTML y restricción por área.
- **Archivos creados/modificados (resumen):**
   - Nuevos: `app/Actions/Reports/BuildDashboardReport.php`, migración `2026_11_20_212650_add_dashboard_report_indexes_to_tasks_and_tickets_tables.php`, `resources/views/components/apex-chart.blade.php`, `tests/Feature/DashboardReportTest.php`.
   - Modificados: `app/Livewire/Dashboard/Index.php`, `resources/views/livewire/dashboard/index.blade.php`, `resources/js/app.js`, `app/Livewire/Boards/Index.php` (corrección de tipo en `historyMonthLabel`), `ia/planificacion.md` e `ia/avances.md`.
- **Pruebas ejecutadas:**
   - `php artisan test --compact tests/Feature/DashboardReportTest.php tests/Feature/DashboardTest.php` → **8/8 en verde, 41 aserciones**.
   - `php artisan test --compact` → **178/178 en verde, 550 aserciones**.
   - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
   - `vendor/bin/pint --dirty --format agent` → aplicado.
   - `npm run build` → correcto (ApexCharts incluido en el bundle).
   - `php artisan migrate --force` → migración aplicada; `SHOW INDEX` confirma los cuatro índices nuevos.
   - Browser logs en `http://localhost:8000/dashboard` → sin errores JS tras la recarga de Vite.
- **Problemas / bloqueos resueltos:**
   - `now()` devuelve `CarbonImmutable`; el bucle de semanas no avanzaba al llamar `addWeek()` sin reasignar y el tipado estricto de `Carbon` rechazaba el valor. Se cambió a `CarbonInterface` y se reasigna el cursor.
   - Un proceso de prueba cancelado dejó un `metadata lock` en `tickets_test`; se liberaron las conexiones antes de repetir la suite.
   - El JSON de configuración dentro de `x-data` con comillas dobles rompía el atributo HTML y causaba `init is not defined` en Alpine; se cambió el atributo a comillas simples y se validó el HTML renderizado.
   - PHPStan detectó propiedades dinámicas de las consultas AVG y comparaciones imposibles; se resolvió leyendo `getAttributes()` y comparando con `''` para claves de colección.
- **Decisiones nuevas / pendientes:**
   - No se crearon permisos nuevos: se reutilizan `reports.view` y `reports.viewOwnArea`.
   - El rango por defecto es últimos 30 días; los filtros inválidos se ignoran de forma segura y un rango invertido se normaliza.
   - ApexCharts se registra una sola vez en `app.js`; cada gráfica usa un `wire:key` distinto para forzar recreación al cambiar filtros.
   - SMTP real, buzón IMAP real, `storage:link`, queue worker y scheduler siguen pendientes para la Fase 7.
- **Próximo paso:** Fase 7 — Hardening y entrega: SMTP real, `storage:link`, queue worker, scheduler, rate limiting, seeders de demostración y verificación final de extremo a extremo.
- **Checklist del plan actualizado:** sí, Fase 6 marcada como completada.

---

## 2026-11-20 — Fases 4 y 5 completadas + optimización del tablero ✅

- **Fase:** 4 y 5 completadas; optimización del tablero entregada; Fase 6 iniciada.
- **Qué se hizo:**
   - Unificado el tablero en 3 columnas (`Por hacer`, `En proceso`, `Terminado`) con selector `Mis tareas | Mi área | Todas` y defaults por rol.
   - Reagrupados `Assigned` en `Por hacer` y `Testing`/`Blocked` en `En proceso`, con badge `Bloqueado` y sin columna `Asignado`.
   - Implementadas subtareas, dependencias `finish_to_start`, tiempos opcionales, vista detalle, comentarios/adjuntos privados, evidencia opcional antes de `Done` y notificación al superior.
   - Creada administración de usuarios, roles y permisos; `colaborador` recibió `tasks.create` y crea siempre en su área, asignado a sí mismo y en `Por hacer`.
   - Rediseñadas las tarjetas: título y avatar colapsados, expansión en sitio, menú de tres puntos, icono `+` por columna, badges corregidos y adjuntos agrupados por tipo con iconos y descarga en pestaña nueva.
   - Optimizado el tablero para grandes volúmenes: búsqueda y filtros, vista Lista, lotes de 50 activas y 20 terminadas recientes, contadores SQL reales, historial por fecha bajo demanda, paginación por cursor y arrastre restringido a terminadas recientes.
   - Añadidos índices `(status, completed_at)`, `(area_id, status, completed_at)`, `(assignee_id, status, completed_at)` y `(status, position, id)`.
   - Confirmado el commit y push de todo lo anterior en `d0d57ad`.
- **Archivos creados/modificados (resumen):**
   - Nuevos: `tests/Feature/BoardHistoryTest.php`, migración `add_performance_indexes_to_tasks_table`, componente `attachment-icons` e iconos Lucide publicados.
   - Modificados: `app/Livewire/Boards/Index.php`, `app/Actions/Tasks/MoveTask.php`, `app/Actions/Tasks/CreateTask.php`, `app/Policies/TaskPolicy.php`, `app/TaskStatus.php`, `app/TicketPriority.php`, seeder de roles, vista `livewire/boards/index.blade.php` y pruebas relacionadas.
- **Pruebas ejecutadas:**
   - `php artisan test --compact` → **172/172 en verde, 512 aserciones**.
   - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
   - `vendor/bin/pint --dirty --format agent` → aplicado.
   - `npm run build` → correcto.
   - `php artisan migrate --force` → migración de índices aplicada; `SHOW INDEX` y `EXPLAIN` verificados.
- **Problemas / bloqueos resueltos:**
   - El primer intento de rollback falló porque el `down()` no era idempotente tras editar una migración ya aplicada; se ajustó con `Schema::hasIndex()` y se reaplicó correctamente.
   - `assertDontSee()` era demasiado amplio en la vista Lista porque el título aparece en atributos del modal; se acotó con `wire:key="list-task-..."`.
- **Decisiones nuevas / pendientes:**
   - Los permisos existentes `reports.view` y `reports.viewOwnArea` se usarán para el alcance del dashboard; no se crearán permisos nuevos.
   - El dashboard seguirá accesible para cualquier usuario autenticado, pero los datos se limitarán por rol: global, por área gestionada o personales.
   - SMTP real, buzón IMAP real, `storage:link`, queue worker y scheduler siguen pendientes para la Fase 7/operación real.
- **Próximo paso:** Fase 6 — ejecutada y documentada en la entrada superior de esta misma fecha.
- **Checklist del plan actualizado:** sí, Fases 4 y 5 marcadas; Fase 6 registrada en la entrada superior.

---

## 2026-11-19 — Fase 3: de correo a ticket y notificación ✅

- **Fase:** 3 (Completada a nivel de implementación y pruebas; SMTP/buzón real pendiente).
- **Qué se hizo:**
   - Creado `CreateTicketFromMessage`: convierte un `MailMessage` en ticket con código secuencial `TKT-0001`, consume asunto/cuerpo/remitente del correo, deduplica por mensaje y autoriza `tickets.create`.
   - Agregada migración que hace único `tickets.mail_message_id` para reforzar la deduplicación en concurrencia.
   - Creado `AssignTicket`: autoriza con `TicketPolicy`, valida area/categoría/prioridad/responsable, exige que el responsable pertenezca al área, fija `assigned_at` una sola vez, crea o reutiliza la tarea raíz y registra la transición inicial en `task_status_transitions`.
   - Creados el evento `TicketAssigned`, el listener `NotifyRequesterTicketAssigned` y el Mailable encolado `TicketAssignedMail` (asunto/cuerpo en español, texto plano + HTML con escape automático vía Blade).
   - Convertida la vista de tickets en un componente Livewire funcional: listado paginado/buscable y formulario de asignación con Flux; agregado botón "Crear ticket" en la bandeja y enlace al listado cuando el mensaje ya fue convertido.
   - Agregadas pruebas de conversión, asignación autorizada/no autorizada, cruce de área, re-asignación, correo con `Mail::fake`, contenido del Mailable, UI de tickets y conversión desde la bandeja.
- **Archivos creados/modificados (resumen):**
   - Nuevos: `app/Actions/Tickets/{CreateTicketFromMessage,AssignTicket}.php`, `app/Events/TicketAssigned.php`, `app/Listeners/NotifyRequesterTicketAssigned.php`, `app/Mail/TicketAssignedMail.php`, `resources/views/emails/tickets/{assigned,assigned-text}.blade.php`, migración `add_unique_mail_message_id_to_tickets_table`, pruebas en `tests/Feature/Tickets/` y `tests/Feature/Mail/TicketAssignedMailTest.php`.
   - Modificados: `app/Livewire/Mail/Inbox.php`, `app/Livewire/Tickets/Index.php`, vistas `livewire/mail/inbox.blade.php` y `livewire/tickets/index.blade.php`, `tests/Feature/Mail/MailInboxTest.php`, `ia/planificacion.md`, `ia/avances.md`.
- **Pruebas ejecutadas:**
   - `php artisan test --compact tests/Feature/Tickets tests/Feature/Mail/TicketAssignedMailTest.php tests/Feature/Mail/MailInboxTest.php` → 19/19 en verde, 63 aserciones.
   - `php artisan test --compact` → **94/94 en verde, 245 aserciones**.
   - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
   - `vendor/bin/pint --format agent` → aplicado; sin cambios pendientes.
   - `php artisan migrate --no-interaction` → migración de unicidad aplicada.
- **Problemas / bloqueos resueltos:**
   - `assertDontSeeInHtml()` escapa el valor esperado, por lo que no sirve para comprobar que el `<script>` crudo no aparece; se validó el HTML renderizado con `render()` y expectativas directas.
   - PHPStan marcó el acceso a la propiedad computada `$this->selectedMessage`; se cambió a la llamada `$this->selectedMessage()` dentro de PHP.
   - `auth()->id()` es `int|string|null` por contrato; `CreateTicketFromMessage::handle()` acepta ese tipo para no forzar un cast artificial.
- **Decisiones nuevas / pendientes:**
   - Defaults aplicados: código `TKT-0001` global, reutilización de `TaskStatus` para el ticket, tarea raíz inicial en `todo`, re-asignación con nuevo correo, aviso solo al remitente y sin notificación in-app.
   - El usuario informó credenciales IMAP en `.env`; la app sigue leyendo IMAP de `mail_accounts` (cifrado en BD, configurado desde "Cuentas de correo"). No se probó conexión real a buzón.
   - `MAIL_MAILER=log` sigue vigente: el correo encolado se verá en logs hasta configurar SMTP real en la Fase 7.
- **Próximo paso:** Fase 4 — tablero Kanban por área con 4 columnas y DnD (`wire:sort`, verificar API con `search-docs`), `MoveTask` con transiciones y `position`, subtareas, dependencias `finish_to_start`, tiempos y vista detalle.
- **Checklist del plan actualizado:** sí.

---

## 2026-11-18 — Fase 2: cliente de correo y bandeja funcional ✅

- **Fase:** 2 (Completada a nivel de implementación y pruebas; conexión a buzón real pendiente).
- **Qué se hizo:**
   - Creada la capa desacoplada `App\Support\Mail`: interfaz `MailClient`, DTOs `InboundMessage`/`InboundAttachment` e implementación `ImapMailClient` con `webklex/php-imap` 6.2.0. Enlazada en `AppServiceProvider`.
   - Creado `ProcessInboundMessage`: deduplicación por `(mail_account_id, folder, external_uid)` y por `message_id`, hilos por `References`/`In-Reply-To`/asunto normalizado, enlace a mensaje padre, almacenamiento de adjuntos en disco privado con nombre/extensión saneados, checksum y límite configurable.
   - Creado `SyncMailAccount`: ejecuta la importación, actualiza `last_synced_at` y registra `mail_sync_runs` con procesados, fallos y error acotado; separa errores de conexión de errores por mensaje.
   - Creados `SyncMailAccountJob` (3 reintentos, backoff, timeout), el comando `tickets:sync-mail` con `--account` y `--due`, y el scheduler cada 5 minutos con `withoutOverlapping(10)` en `routes/console.php`.
   - Convertida la UI de cuentas en un componente Livewire funcional: alta/edición, credenciales cifradas por el cast del modelo, prueba de conexión IMAP y sincronización manual; agregada al sidebar y a `routes/web.php`.
   - Convertida la bandeja en una vista Livewire con selección de cuenta, paginación, búsqueda, filtro de no leídos, detalle del mensaje, hilos, marcado como leído, cuerpo en texto plano (sin inyectar HTML) y lista de adjuntos.
   - Agregada la ruta autorizada `attachments.download`, que usa `Gate::authorize`, verifica existencia en disco y fuerza `Content-Disposition: attachment`; ampliada `AttachmentPolicy` con `attachments.view`.
   - Agregada configuración `mail.inbound` (carpeta, lote, timeout, disco y tamaño máximo) y corregida `AttachmentFactory` para usar el disco real `local`.
   - Creado `FakeMailClient` en `tests/Support` y pruebas de sincronización, deduplicación, hilos, límite de adjuntos, fallos, cifrado/UI, cola/comando, bandeja, descarga y navegación.
- **Archivos creados/modificados (resumen):**
   - Nuevos: `app/Support/Mail/{MailClient,InboundMessage,InboundAttachment,ImapMailClient}.php`, `app/Actions/Mail/{ProcessInboundMessage,SyncMailAccount}.php`, `app/Jobs/SyncMailAccountJob.php`, `app/Console/Commands/SyncMailCommand.php`, `app/Http/Controllers/AttachmentDownloadController.php`, `app/Policies/MailMessagePolicy.php`.
   - Modificados: `app/Livewire/Mail/Accounts.php`, `app/Livewire/Mail/Inbox.php`, `app/Providers/AppServiceProvider.php`, `app/Policies/AttachmentPolicy.php`, `config/mail.php`, `routes/web.php`, `routes/console.php`, `database/factories/AttachmentFactory.php`, `resources/views/layouts/app/sidebar.blade.php`, vistas `livewire/mail/{accounts,inbox}.blade.php`.
   - Pruebas: `tests/Support/FakeMailClient.php`, `tests/Feature/Mail/{SyncMailAccountTest,MailAccountsUiTest,MailInboxTest}.php`, `tests/Feature/NavigationTest.php`.
   - Documentación: `ia/planificacion.md`, `ia/avances.md`.
- **Pruebas ejecutadas:**
   - `php artisan test --compact tests/Feature/Mail tests/Feature/NavigationTest.php` → 26/26 en verde, 69 aserciones.
   - `php artisan test --compact` → **81/81 en verde, 196 aserciones**.
   - `vendor/bin/phpstan analyse --no-progress` → **0 errores**.
   - `vendor/bin/pint --format agent` → aplicado; sin cambios pendientes.
- **Problemas / bloqueos resueltos:**
   - El controlador base no usa `AuthorizesRequests`; se cambió `$this->authorize()` por `Gate::authorize()`.
   - Livewire 4 no inyecta dependencias en acciones arbitrarias; `MailClient` se inyecta ahora en el método `testConnection()`.
   - PHPStan detectó un `instanceof` siempre verdadero en `getDate()` de Webklex; se normalizó con `->first()` sin ignores ni baselines.
   - `AttachmentFactory` apuntaba a un disco `private` inexistente; se ajustó a `local`.
   - No hay credenciales IMAP/SMTP de prueba: toda la lógica se validó con el cliente falso, sin conexiones de red.
- **Decisiones nuevas / pendientes:**
   - D8 queda implementada con disco privado, límite de 25 MB por defecto y descarga autorizada; todavía se recomienda una política MIME/allowlist y almacenamiento remoto antes de producción.
   - El correo entrante se muestra como texto plano; no se instaló sanitizador HTML porque no se inyecta HTML entrante.
   - La verificación de correos de la Fase 1 no se modificó; SMTP real sigue pendiente para la Fase 3/7.
- **Próximo paso:** Fase 3 — convertir mensajes de la bandeja en tickets, asignar área/categoría/prioridad/responsable, crear la tarea raíz y notificar por correo al remitente (con `Mail::fake()` en pruebas). Confirmar credenciales reales antes de probar IMAP/SMTP contra un buzón.
- **Checklist del plan actualizado:** sí.

---

## 2026-11-18 — Desbloqueo de acceso por verificación de correo en desarrollo ✅

- **Fase:** Ajuste de desarrollo sobre Fase 1.
- **Problema reportado:** al registrarse, la app indicaba que se envió el correo de verificación, pero nunca llegaba a la bandeja y el usuario no podía pasar de la pantalla de verificación.
- **Causa confirmada:** `.env` tiene `MAIL_MAILER=log` (el correo se escribe en logs, no se envía), Fortify tiene activa `emailVerification()`, `User` implementa `MustVerifyEmail` y las rutas protegidas exigían el middleware `verified`.
- **Decisión del usuario:** usar **modo desarrollo**, haciendo opcional la verificación solo en local y manteniéndola obligatoria en producción.
- **Qué se hizo:**
  - Agregada la configuración `app.email_verification_required` en `config/app.php`, con valor por defecto `true`.
  - Agregado `EMAIL_VERIFICATION_REQUIRED=false` a `.env` y `EMAIL_VERIFICATION_REQUIRED=true` a `.env.example`.
  - Fijado `EMAIL_VERIFICATION_REQUIRED=true` en `phpunit.xml` para que las pruebas no dependan del `.env` local.
  - Creado el middleware `App\Http\Middleware\EnsureEmailVerificationRequired`, que delega en `EnsureEmailIsVerified` solo cuando el flag está activo; evalúa la configuración en cada request, no al cargar rutas.
  - Registrado el alias `verified_if_required` en `bootstrap/app.php`.
  - Actualizadas `routes/web.php` y `routes/settings.php` para usar el nuevo alias en las rutas protegidas.
  - Creado `tests/Feature/EmailVerificationModeTest.php` con los dos estados: verificación opcional y obligatoria.
  - Ejecutado `php artisan optimize:clear`; `php artisan config:show app.email_verification_required` devuelve `false` en local.
- **Archivos creados/modificados:**
  - `app/Http/Middleware/EnsureEmailVerificationRequired.php` (nuevo).
  - `bootstrap/app.php`, `config/app.php`, `routes/web.php`, `routes/settings.php`.
  - `.env`, `.env.example`, `phpunit.xml`.
  - `tests/Feature/EmailVerificationModeTest.php` (nuevo).
  - `ia/avances.md`.
- **Pruebas ejecutadas:**
  - `php artisan test --compact tests/Feature/EmailVerificationModeTest.php tests/Feature/Auth/EmailVerificationTest.php` → 7/7 en verde.
  - `php artisan test --compact` → **62/62 en verde, 140 aserciones**.
  - `vendor/bin/pint --format agent` → sin cambios pendientes.
  - `vendor/bin/phpstan analyse --no-progress` → 0 errores.
- **Problemas / bloqueos:**
  - El primer intento condicionaba el middleware al registrar las rutas, por lo que `config()->set()` en el test no podía cambiarlo en caliente. Se resolvió moviendo la decisión al middleware.
  - No se desactivó `MustVerifyEmail` ni `Features::emailVerification()`; producción sigue protegida porque el flag por defecto es `true`.
- **Decisiones nuevas:** verificación opcional solo cuando `EMAIL_VERIFICATION_REQUIRED=false`. Para correos reales, configurar SMTP en una fase posterior sin modificar este mecanismo.
- **Próximo paso:** recargar la aplicación o cerrar sesión y volver a entrar con el usuario ya registrado; debería acceder al dashboard. Después continuar con la Fase 2 (cliente de correo).
- **Checklist del plan actualizado:** no fue necesario; el plan ya contempla SMTP real como pendiente de entorno.

---

## 2026-11-18 — Fase 1: cimientos del sistema ✅

- **Fase:** 1 (Completada).
- **Qué se hizo:**
  - Registradas las decisiones del usuario: instalar las tres dependencias propuestas, usar usuario/contraseña cifrados para correo y empezar la Fase 1.
  - Instalados `spatie/laravel-permission` 8.5.0, `webklex/php-imap` 6.2.0 y `apexcharts` npm 8.9.1; publicados `config/permission.php` y la migración de Spatie.
  - Creadas y ejecutadas 13 migraciones de dominio: áreas, categorías, campos organizacionales en `users`, cuentas de correo, mensajes, adjuntos, tickets, tareas, dependencias, transiciones de estado, comentarios, ejecuciones de sincronización y notificaciones.
  - Creados los Enums `App\TaskStatus` y `App\TicketPriority`, con etiquetas, agrupación Kanban y estados finales.
  - Creados modelos y relaciones Eloquent para todas las entidades; `User` usa `HasRoles`, `BelongsTo<Area>` y `HasMany<Task>`. Las credenciales IMAP usan cast `encrypted` y quedan ocultas en serialización.
  - Creadas factories para todos los modelos nuevos, con estados útiles (`assigned`, `inProgress`, `done`, `childOf`, `managedBy`, `internal`, `fromEmail`, etc.) y estados de rol en `UserFactory` (`superAdmin`, `mailManager`, `jefeArea`, `colaborador`, `consultor`), usados en las pruebas.
  - Creados `RolesAndPermissionsSeeder` y `DemoDataSeeder`; `DatabaseSeeder` ahora los invoca. Ambos son idempotentes (`firstOrCreate`/`syncRoles`).
  - Creadas `AreaPolicy`, `TicketPolicy`, `TaskPolicy`, `CommentPolicy`, `AttachmentPolicy` y `MailAccountPolicy`. Laravel 13 las descubre por convención, sin registro manual.
  - Creados componentes Livewire de clase para Dashboard, Bandeja, Tickets, Tableros y Mis tareas, con vistas Flux de marcador de posición y KPIs básicos en el dashboard.
  - Agregadas rutas autenticadas/verificadas: `dashboard`, `mail.inbox`, `tickets.index`, `boards.index`, `tasks.mine`.
  - Actualizada la navegación Flux del sidebar para mostrar cada enlace solo según permisos Spatie.
  - Actualizado `ia/planificacion.md`: estado real de dependencias, decisiones D1–D10, fases 0 y 1 marcadas y Enums con su namespace real.
- **Archivos creados/modificados (resumen):**
  - `app/TaskStatus.php`, `app/TicketPriority.php`.
  - `app/Models/`: `Area`, `TicketCategory`, `MailAccount`, `MailMessage`, `Attachment`, `Ticket`, `Task`, `TaskDependency`, `TaskStatusTransition`, `Comment`, `MailSyncRun`, `User`.
  - `app/Policies/`: seis policies nuevas.
  - `app/Livewire/`: `Mail/Inbox`, `Tickets/Index`, `Boards/Index`, `Tasks/Mine`, `Dashboard/Index`.
  - `database/migrations/`: 13 migraciones nuevas (ya aplicadas).
  - `database/factories/`: 11 factories nuevas.
  - `database/seeders/`: `RolesAndPermissionsSeeder`, `DemoDataSeeder`, `DatabaseSeeder`.
  - `resources/views/livewire/`: cinco vistas nuevas; `resources/views/layouts/app/sidebar.blade.php`.
  - `routes/web.php`, `phpunit.xml`, `composer.json`, `package.json`, `ia/planificacion.md`, `ia/avances.md`.
- **Pruebas ejecutadas:**
  - `php artisan test --compact tests/Feature/Seeders tests/Feature/Policies tests/Feature/NavigationTest.php tests/Feature/DashboardTest.php` → 24/24 en verde.
  - `php artisan test --compact` → **60/60 en verde, 137 aserciones**.
  - `vendor/bin/phpstan analyse --no-progress` → **0 errores** (corregidos los genéricos de relaciones con `$this` como modelo declarante).
  - `vendor/bin/pint --format agent` → aplicado; `--dirty` no está disponible porque el proyecto no es repositorio Git.
  - `php artisan migrate:status` → 19 migraciones aplicadas, incluidas las 13 nuevas.
  - `php artisan route:list` → rutas nuevas registradas vía `Route::livewire()`.
- **Problemas / bloqueos resueltos:**
  - Pest fallaba con `could not find driver (sqlite)` porque PHP no tiene `pdo_sqlite`. Se creó la BD `tickets_test` en MariaDB y se ajustó `phpunit.xml` a `DB_CONNECTION=mariadb` / `DB_DATABASE=tickets_test`. No se tocó `.env`.
  - Dos políticas inicialmente demasiado permisivas (`jefe_area` podía editar cualquier área; `colaborador` no podía actualizar sus tareas). Se corrigieron los permisos del seeder y la matriz quedó cubierta por tests.
  - PHPStan marcó 50 errores de genéricos en relaciones Eloquent. Se documentó cada relación con `BelongsTo<…, $this>`, `HasMany<…, $this>`, etc.; no se usaron ignores ni baselines.
  - No se ejecutó `migrate:fresh` contra la BD local para no destruir datos existentes; la cobertura se validó con `RefreshDatabase`.
- **Decisiones nuevas:**
  - D2 ajustada: MariaDB también para pruebas por falta de `pdo_sqlite`.
  - D4–D10 se mantienen como valores por defecto y deben revisarse al iniciar cada fase.
  - Se adopta `App\TaskStatus` / `App\TicketPriority` (no `App\Enums\...`) para coincidir con la estructura creada.
- **Próximo paso:** empezar Fase 2 — configurar UI de `mail_accounts`, implementar `MailClient` + `ImapMailClient`, job/comando de sincronización, deduplicación de mensajes/hilos y bandeja Livewire con adjuntos en disco privado. Confirmar credenciales IMAP/SMTP de prueba antes de conectar a un buzón real.
- **Checklist del plan actualizado:** sí.

---

## 2026-11-18 — Análisis inicial y planificación ✅

- **Fase:** Planificación (Fase 0 iniciada, sin código de dominio).
- **Qué se hizo:**
  - Leído `ia/requerimientos.md` (sistema de tickets con bandeja de correo, Kanban, subtareas/dependencias, notificaciones, dashboard).
  - Verificado el estado real del proyecto: Laravel 13.43, Livewire 4.1, Flux 2.13, Tailwind 4, Fortify ya instalados; MariaDB en `.env`; colas en `database`.
  - Confirmado que **no** están instalados: Spatie Permissions, cliente IMAP, ApexCharts.
  - Detectado que los componentes Livewire existentes son **basados en clase** (`app/Livewire/...`), no SFC.
  - Redactado `ia/planificacion.md` con propuesta de arquitectura, modelo de datos, roles, 8 fases, criterios de aceptación, estrategia de pruebas y handoff.
  - Creado este archivo de avances.
- **Hallazgos / riesgos:**
  - `AGENTS.md` prohíbe cambiar dependencias sin aprobación → la Fase 0 es bloqueante.
  - `.env` tiene credenciales reales de MariaDB; `.env.example` dice SQLite → confirmar entorno.
  - `MAIL_MAILER=log`: los correos de notificación no saldrán hasta configurar SMTP.
  - No existe `config/livewire.php`; conviene seguir la convención de clase existente en vez del default SFC de Livewire 4.
  - No existe `.ai/rules/`; las reglas vigentes están en `AGENTS.md` y las skills en `.agents/skills/`.
- **Decisiones tomadas:** Ninguna definitiva. Propuestas pendientes D1–D10 en `ia/planificacion.md`.
- **Próximo paso:** Obtener aprobación de D1–D10, actualizar el plan si cambia algo y empezar la Fase 1 (instalar dependencias autorizadas + migraciones/modelos/seeders base).
- **Comandos ejecutados (solo lectura):**
  - `php artisan --version`
  - `php artisan route:list --except-vendor`
  - `php artisan migrate:status`

---

## Plantilla para la próxima entrada

```markdown
## YYYY-MM-DD — Fase X: título corto

- **Fase:** X (En curso / Completada / Bloqueada).
- **Qué se hizo:**
  - ...
- **Archivos creados/modificados:**
  - ...
- **Pruebas ejecutadas:** (comando + resultado)
- **Problemas / bloqueos:**
  - ...
- **Decisiones nuevas:** ...
- **Próximo paso:** ...
- **Checklist del plan actualizado:** sí / no
```
