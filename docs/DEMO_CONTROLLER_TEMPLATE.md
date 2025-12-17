# SentryDemoController Template

Este archivo contiene el código del controlador de ejemplo que debe añadirse a cada demo para mostrar todos los casos de uso del servicio `SentryErrorReporter`.

## Ubicación

El controlador debe crearse en:
- `demo/demo-symfony7/src/Controller/SentryDemoController.php`
- `demo/demo-symfony8/src/Controller/SentryDemoController.php`
- `demo/demo-symfony8-php85/src/Controller/SentryDemoController.php`

## Casos de Uso Implementados

El controlador incluye ejemplos de todos los métodos del servicio:

1. **captureException()** - `/sentry/capture-exception`
   - Captura excepciones de forma segura
   - Incluye contexto y mensaje personalizado

2. **captureMessage()** - `/sentry/capture-message`
   - Captura mensajes con diferentes niveles de severidad
   - Soporta parámetro `?level=debug|info|warning|error|fatal`

3. **captureError()** - `/sentry/capture-error`
   - Método de conveniencia para capturar errores

4. **addBreadcrumb()** - `/sentry/add-breadcrumb`
   - Añade breadcrumbs para tracking de acciones

5. **setUser()** - `/sentry/set-user`
   - Establece contexto de usuario

6. **setContext()** - `/sentry/set-context`
   - Establece contexto adicional

7. **complete-example** - `/sentry/complete-example`
   - Ejemplo completo combinando todas las características

8. **safe-operation** - `/sentry/safe-operation`
   - Demuestra que el servicio nunca rompe la aplicación

## Instalación

Si hay problemas de permisos, puedes crear el archivo manualmente o usar:

```bash
# Para cada demo
cd demo/demo-symfony7
mkdir -p src/Controller
# Copiar el contenido del controlador
```

El código completo está disponible en el archivo fuente del controlador.

