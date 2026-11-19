# Sistema de Tickets
Se requiere se realice en laravel 13 con el stack de Livewire 4 TailwindCss 4 FluxUi y cualquier libreria o dependencia que haga falta.
Para el manejor de Permisos se utilizara laravel Permissions Spatie. para el tema de graficas se utilizara ApexChartJs.

El actual proyecto es un proyecto completamente nuevo ya con el Stack instalado.

La idea inicial es tener primero un cliente de correo en la cual se pueda configurar una cuenta de correo, esta cuenta de correo de la cual se leeran correos con archivos adjuntos hilos etc. y servira para que se pueda desde esta bandeja poder asignar tickets a diferentes areas o categorias y a personas en especifico.
Cuando a un area o categoria se le asigne un ticket este caera en un canban de 4 pizarras, Tareas, Asignado,En proceso, Terminado. El usuario responsable del area o categoria (jefe de area) sera el encargado de en esa misma pizzara de Tareas, podra crear o desglozar sub-tareas de esta misma tarea y asignar cada sub-tarea a cada uno de los colaboradores por debajo de el, o tambien podra asignar la tarea sin crear subtareas a un usuario, se podran colocar Tiempos para las tareas aun que seran opcionales, y lo mismo para las subtarea, asi mismo las subtareas seran o no dependientes, o independientes, convinaremos el kanban con un manejador de proyectos donde la tarea principal sera un proyecto y se puede asignar sub-tareas que dependan de otras. Al asingar las tareas y subtarea se podra subir todo tipo de recursos Documentos de Word, Excel, Imagenes, PDF, Presentaciones, Fotos, cada Subtarea tendra un titulo y una descripcion larga, algo similiar pero no tan completo como Trello, y pues cuando se asigne una tarea o subtarea a un usuario a este tambien le caiga en su pizzara de tarea y esta la pueda arrastras o colocar en la pizarra de en proceso, se capture las fechas en las cuales pasa de un estado a otro, este pueda dejar comentarios o descripciones, asi mismo capturas, fotos, links o archivos, esta retroalimentacion podra ser vista por el jefe o superior del usuario o alguien designado. Como este sistema esta mas enfocado a un area de Informatica Tecnicos y Desarrolladores de sofware entonces pensar tambien en algun estado como de pruebas antes de ser terminada la tarea.

El usuario encargado de asignar tickets al momento de asignar ticket oste envie un correo de notificacion al remitente donde se notifique que el ticket ya fue asignado.

Se tendra que llevar un Dashboar con graficas y kpis que indiquen los avances y progresos de los tickets asi mismo las cantidades porcentajes.
Los ultimos tickets, las categorias de ticket por prioridad. y todo los indicadores para los mejores analisis.

La idea puede ir mejorando y se acepta opiniones.
