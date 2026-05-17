=== EWEB - MC Video Popup ===
Contributors: yisusdevelop
Tags: popup, video, youtube, autoplay, modal
Requires at least: 6.1
Tested up to: 6.6
Stable tag: 1.1.4
Requires PHP: 7.4.33
License: GPLv2 or later

Popup de vídeo de YouTube con reproducción automática, selección por idioma (ES/EN), cierre solo con "X" (opcionalmente abre una URL en nueva pestaña), recuerdo para no mostrarlo durante N horas y panel para activarlo globalmente.

== Uso rápido ==
1. Sube la carpeta "eweb-mc-video-popup" a "/wp-content/plugins/" y actívalo.
2. Ve a **Ajustes > EWEB - MC Video Popup** y completa:
   - Activar globalmente
   - Vídeo ES / EN (YouTube)
   - URL destino al cerrar (X)
   - Modo idioma (navegador o idioma del sitio)
   - "No repetir durante N horas" (opcional). Para pruebas usa `?mcvp=1`; para suprimir, `?mcvp=0`.
   - Solo portada / excluir rutas si lo necesitas
3. (Opcional) Shortcode `[eweb_mc_video_popup]` para colocarlo en páginas concretas u overrides (usa la configuración del admin como base).

== Notas ==
- **Autoplay**: los navegadores lo permiten si el vídeo inicia mute; se incluye botón "Activar sonido".
- **Overlay**: no cierra. Solo la **X** cierra el modal.
- **Privacidad**: si tu CMP lo exige, retrasa la inyección hasta consentimiento o usa `youtube-nocookie.com`.
- **QA**: prueba en móvil real y escritorio. `?mcvp=1` fuerza mostrar aunque esté recordado; `?mcvp=0` lo suprime.

== Timeline de cambios ==
- **2025-11-25 (Gemini)**: v1.1.4 - CSS ISOLATION: Complete CSS rewrite with !important rules and explicit resets to prevent Elementor Hello and other themes from affecting popup styles. Added responsive improvements and accessibility enhancements.
- **2025-11-25 (Gemini)**: v1.1.3 - CRITICAL FIX: Popup now hidden by default in CSS and only shown when JavaScript explicitly activates it. This fixes the issue where popup appeared even when persistence system said it shouldn't (after being recently closed). Added 'mcvp-active' class control system.
- **2025-11-25 (Gemini)**: v1.1.2 - Fixed critical JavaScript syntax error (duplicated functions) that prevented popup from closing. Added comprehensive console logging for debugging persistence and video loading issues. Enhanced error handling for YouTube API and video ID extraction.
- **2025-11-21 (Codex)**: Refactor de `wp_enqueue_scripts` para volver a inyectar los assets completos antes del `<head>`, se añadió `maybe_prepare_assets()` y helpers que evitan duplicar lógica entre shortcode y render global. Se cambió el `option_name` a `eweb_mc_video_popup_options`, se actualizó la cabecera del plugin (autor, PHP 7.4.33 temporal) y se liberó la versión 1.1.1 con informe embebido.
- **2025-11-21 (Codex)**: Se añadió fallback de persistencia (cookies + localStorage) para el recuerdo del popup, y se centró el botón "Activar sonido" evitando que el clic pause el video. Pendiente validar en producción, ya que el popup sigue reabriéndose al cerrar según pruebas del usuario.
