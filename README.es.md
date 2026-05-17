# EWEB - MC Video Popup

## Resumen

`EWEB - MC Video Popup` muestra un popup de video de YouTube con autoplay (en mute), seleccion por idioma (ES/EN), control de persistencia y activacion global desde el panel de WordPress.

## Funcionalidad principal

- Activacion/desactivacion global desde ajustes.
- Seleccion de video por idioma del navegador o idioma del sitio.
- Persistencia opcional para no repetir durante N horas.
- Redireccion opcional al cerrar con el boton `X`.
- Capa clicable opcional sobre el video.
- Shortcode opcional para uso puntual: `[eweb_mc_video_popup]`.
- Banderas para QA:
  - `?mcvp=1` fuerza mostrar.
  - `?mcvp=0` suprime mostrar.

## Notas tecnicas

- WordPress: `6.1+`
- PHP: `7.4.33+` (compatibilidad temporal con hosting legacy)
- Text Domain: `eweb-mc-video-popup`
- Version actual: `1.1.4`
- Option key: `eweb_mc_video_popup_options`

## Campos de configuracion relevantes

- `enabled`, `only_home`, `exclude_paths`
- `video_es`, `video_en`, `target`
- `show_once`, `hours`, `start`
- `mute`, `controls`
- `language_mode`, `open_on_close`, `toplink_enabled`
- `disable_for_admin`, `debug_mode`

## Estructura

- `mc-video-popup.php`: bootstrap, settings, render y shortcode.
- `assets/css/popup.css`: estilos del popup.
- `assets/js/popup.js`: logica de popup/persistencia/reproductor.
- `readme.txt`: metadatos estilo WordPress y changelog.

## Estado de validacion (AI-Vault)

- Sintaxis PHP: OK
- PHPCS: ejecutado, con deuda de estilo/documentacion
- Tests unitarios: no disponibles todavia
