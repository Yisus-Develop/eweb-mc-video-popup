<?php
/**
 * Plugin Name: EWEB - MC Video Popup
 * Plugin URI: https://enlaweb.co/plugins/eweb-mc-video-popup
 * Description: Video (YouTube) popup with automatic playback, language selection (ES/EN), close only with "X" (optionally opens a URL in a new tab) and panel to activate it globally.
 * Version: 1.1.4
 * Requires at least: 6.1
 * Requires PHP: 7.4.33
 * Tested up to: 6.6
 * Author: Yisus Develop
 * Author URI: https://github.com/Yisus-Develop
 * License: GPLv2 or later
 * Text Domain: eweb-mc-video-popup
 * Domain Path: /languages
 */

/**
 * Informe IA (Codex - 2025-11-21):
 * - Se refactorizó el cargado de assets para inyectarlos en wp_enqueue_scripts y evitar estilos rotos en frontend.
 * - Se creó el helper maybe_prepare_assets() y se reutiliza la misma configuración en shortcode y render global.
 * - Se cambió el option key a eweb_mc_video_popup_options para aislar esta versión del plugin original.
 * - Se actualizaron los metadatos (PHP 7.4.33 temporal, autor Yisus Develop) y se elevó la versión a 1.1.1.
 */

if (!defined('ABSPATH')) { exit; }

final class MC_Video_Popup {
  // Temporary constraint: production host is locked to PHP 7.4.33 (legacy stack); keep compatibility until infra upgrade.
  const SLUG   = 'eweb-mc-video-popup';
  const OPTKEY = 'eweb_mc_video_popup_options';
  static $ran  = false; // evita doble inyección
  private static $prepared_cfg = null;

  public static function init(){
    add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_prepare_assets'], 20);
    add_shortcode('eweb_mc_video_popup', [__CLASS__, 'shortcode']);

    // Inyección global según opciones
    add_action('wp_footer', [__CLASS__, 'maybe_render'], 20);

    // Admin
    add_action('admin_menu',  [__CLASS__, 'admin_menu']);
    add_action('admin_init',  [__CLASS__, 'admin_init']);
  }

  /* =================== Options =================== */
  public static function defaults(){
    return [
      'enabled'         => 0,
      'only_home'       => 0,
      'exclude_paths'   => '',
      'video_es'        => '',
      'video_en'        => '',
      'target'          => 'https://marschallenge.space',
      'show_once'       => 1,
      'hours'           => 24,
      'start'           => 0,
      'mute'            => 1,
      'controls'        => 1,
      'language_mode'   => 'browser', // browser|site
      'open_on_close'   => 1,
      'toplink_enabled' => 0,
      'disable_for_admin' => 0,
      'debug_mode' => 0,
    ];
  }

  public static function opts(){
    $opt = get_option(self::OPTKEY, []);
    return wp_parse_args(is_array($opt)? $opt : [], self::defaults());
  }

  public static function sanitize($input){
    $d = self::defaults();
    $out = [];
    $out['enabled']         = empty($input['enabled'])? 0 : 1;
    $out['only_home']       = empty($input['only_home'])? 0 : 1;
    $out['exclude_paths']   = isset($input['exclude_paths'])? sanitize_text_field($input['exclude_paths']) : '';
    $out['video_es']        = isset($input['video_es'])? esc_url_raw($input['video_es']) : '';
    $out['video_en']        = isset($input['video_en'])? esc_url_raw($input['video_en']) : '';
    $out['target']          = isset($input['target'])? esc_url_raw($input['target']) : $d['target'];
    $out['show_once']       = empty($input['show_once'])? 0 : 1;
    $out['hours']           = max(0, intval($input['hours'] ?? $d['hours']));
    $out['start']           = max(0, intval($input['start'] ?? $d['start']));
    $out['mute']            = empty($input['mute'])? 0 : 1;
    $out['controls']        = empty($input['controls'])? 0 : 1;
    $mode                   = in_array($input['language_mode'] ?? '', ['browser','site'], true) ? $input['language_mode'] : $d['language_mode'];
    $out['language_mode']   = $mode;
    $out['open_on_close']   = empty($input['open_on_close'])? 0 : 1;
    $out['toplink_enabled'] = empty($input['toplink_enabled'])? 0 : 1;
    $out['disable_for_admin'] = empty($input['disable_for_admin'])? 0 : 1;
    $out['debug_mode'] = empty($input['debug_mode'])? 0 : 1;
    return $out;
  }

  /* =================== Assets =================== */
  public static function register_assets(){
    $ver = '1.1.0';
    $base = plugin_dir_url(__FILE__);
    wp_register_style(self::SLUG, $base . 'assets/css/popup.css', [], $ver);
    wp_register_script(self::SLUG, $base . 'assets/js/popup.js', [], $ver, true);
  }

  private static function enqueue_with_cfg($cfg){
    wp_enqueue_style(self::SLUG);
    wp_enqueue_script(self::SLUG);
    wp_add_inline_script(self::SLUG, 'window.MCVideoPopupCFG = ' . wp_json_encode($cfg) . ';', 'before');
  }

  private static function build_cfg_from_options($o){
    return [
      'hours'          => (int)$o['hours'],
      'showOnce'       => (bool)$o['show_once'],
      'start'          => (int)$o['start'],
      'mute'           => (bool)$o['mute'],
      'controls'       => (bool)$o['controls'],
      'languageMode'   => $o['language_mode'],
      'openOnClose'    => (bool)$o['open_on_close'],
      'toplinkEnabled' => (bool)$o['toplink_enabled'],
      'debugMode'      => (bool)$o['debug_mode'],
    ];
  }

  private static function should_render($o){
    if ($o['disable_for_admin'] && current_user_can('manage_options')) {
      return false;
    }
    if (!$o['enabled']) {
      return false;
    }
    if (isset($_GET['mcvp']) && $_GET['mcvp'] === '0') {
      return false;
    }
    if ($o['only_home'] && !is_front_page()) {
      return false;
    }
    if ($o['exclude_paths']) {
      $req = $_SERVER['REQUEST_URI'] ?? '';
      foreach (explode(',', $o['exclude_paths']) as $frag) {
        $frag = trim($frag);
        if ($frag !== '' && strpos($req, $frag) !== false) {
          return false;
        }
      }
    }
    return true;
  }

  public static function maybe_prepare_assets(){
    $o = self::opts();
    if (!self::should_render($o)) {
      return;
    }
    self::$prepared_cfg = self::build_cfg_from_options($o);
    self::enqueue_with_cfg(self::$prepared_cfg);
  }

  /* =================== Render =================== */
  private static function render_markup($video_es, $video_en, $target, $cfg){
    if (self::$ran) return; self::$ran = true;
    ?>
    <div class="mcvp-wrap" data-target="<?php echo esc_attr($target); ?>" data-video-es="<?php echo esc_attr($video_es); ?>" data-video-en="<?php echo esc_attr($video_en); ?>">
      <div class="mcvp-overlay" aria-hidden="true"></div>
      <div class="mcvp-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Video Mars Challenge', 'eweb-mc-video-popup'); ?>">
        <button class="mcvp-close" aria-label="<?php echo esc_attr__('Cerrar', 'eweb-mc-video-popup'); ?>">×</button>

        <div class="mcvp-player">
          <div id="mcvp-player-slot"></div>
          <a class="mcvp-toplink" href="<?php echo esc_attr($target); ?>" target="_blank" rel="noopener">&nbsp;</a>
          <button class="mcvp-unmute" aria-label="<?php echo esc_attr__('Activar sonido','eweb-mc-video-popup'); ?>"><?php echo esc_html__('Activar sonido','eweb-mc-video-popup'); ?></button>
        </div>
        <p class="mcvp-note"><?php echo esc_html__('El vídeo empieza en silencio para garantizar la reproducción automática. Puedes activar el sonido.','eweb-mc-video-popup'); ?></p>
      </div>
    </div>
    <?php
  }

  public static function shortcode($atts = []){
    $o = self::opts();
    $a = shortcode_atts([
      'video_es'  => $o['video_es'],
      'video_en'  => $o['video_en'],
      'target'    => $o['target'],
      'show_once' => $o['show_once'] ? '1' : '0',
      'hours'     => (string)$o['hours'],
      'start'     => (string)$o['start'],
      'mute'      => $o['mute'] ? '1' : '0',
      'controls'  => $o['controls'] ? '1' : '0',
    ], $atts, 'eweb_mc_video_popup');

    $cfg = [
      'hours'          => max(0, intval($a['hours'])),
      'showOnce'       => $a['show_once'] === '1',
      'start'          => max(0, intval($a['start'])),
      'mute'           => $a['mute'] === '1',
      'controls'       => $a['controls'] === '1',
      'languageMode'   => $o['language_mode'],
      'openOnClose'    => (bool)$o['open_on_close'],
      'toplinkEnabled' => (bool)$o['toplink_enabled'],
    ];

    self::enqueue_with_cfg($cfg);

    ob_start();
    self::render_markup(
      esc_url($a['video_es']),
      esc_url($a['video_en']),
      esc_url($a['target']),
      $cfg
    );
    return ob_get_clean();
  }

  public static function maybe_render(){
    $o = self::opts();
    if (!self::should_render($o)) {
      return;
    }

    if (self::$ran) return;

    $cfg = self::$prepared_cfg ?: self::build_cfg_from_options($o);
    if (!self::$prepared_cfg) {
      self::enqueue_with_cfg($cfg);
    }

    self::render_markup(
      esc_url($o['video_es']),
      esc_url($o['video_en']),
      esc_url($o['target']),
      $cfg
    );
  }

  /* =================== Admin =================== */
  public static function admin_menu(){
    add_options_page(
      __('MC Video Popup','eweb-mc-video-popup'),
      __('MC Video Popup','eweb-mc-video-popup'),
      'manage_options',
      self::SLUG,
      [__CLASS__, 'settings_page']
    );
  }

  public static function admin_init(){
    register_setting(self::SLUG, self::OPTKEY, [__CLASS__, 'sanitize']);

    add_settings_section('mcvp_main', __('Ajustes generales','eweb-mc-video-popup'), function(){
      echo '<p>'.esc_html__('Configura el popup global. También puedes usar el shortcode para casos puntuales.','eweb-mc-video-popup').'</p>';
    }, self::SLUG);

    self::add_field('enabled', __('Activar globalmente','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[enabled]" value="1" '.checked(1,$o['enabled'],false).'/> '.__('Inyectar el popup en todo el sitio','eweb-mc-video-popup').'</label>';
    });

    self::add_field('only_home', __('Solo en portada','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[only_home]" value="1" '.checked(1,$o['only_home'],false).'/> '.__('Mostrar únicamente en la página de inicio','eweb-mc-video-popup').'</label>';
    });

    self::add_field('exclude_paths', __('Excluir rutas','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<input type="text" class="regular-text" name="'.self::OPTKEY.'[exclude_paths]" value="'.esc_attr($o['exclude_paths']).'" placeholder="/gracias, /legal"/> <p class="description">'.__('Subcadenas de URL separadas por coma. Si coinciden, no se muestra.','eweb-mc-video-popup').'</p>';
    });

    self::add_field('video_es', __('Vídeo en Español','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<input type="url" class="regular-text" name="'.self::OPTKEY.'[video_es]" value="'.esc_attr($o['video_es']).'" placeholder="https://www.youtube.com/watch?v=..."/>';
    });

    self::add_field('video_en', __('Vídeo en Inglés','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<input type="url" class="regular-text" name="'.self::OPTKEY.'[video_en]" value="'.esc_attr($o['video_en']).'" placeholder="https://www.youtube.com/watch?v=..."/>';
    });

    self::add_field('target', __('URL al cerrar (X)','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<input type="url" class="regular-text" name="'.self::OPTKEY.'[target]" value="'.esc_attr($o['target']).'" placeholder="https://marschallenge.space"/>';
    });

    self::add_field('language_mode', __('Detección de idioma','eweb-mc-video-popup'), function(){
      $o=self::opts();
      echo '<select name="'.self::OPTKEY.'[language_mode]">';
      echo '<option value="browser" '.selected('browser',$o['language_mode'],false).'>'.__('Navegador','eweb-mc-video-popup').'</option>';
      echo '<option value="site" '.selected('site',$o['language_mode'],false).'>'.__('Idioma del sitio (html lang)','eweb-mc-video-popup').'</option>';
      echo '</select>';
    });

    self::add_field('show_once', __('No repetir durante N horas','eweb-mc-video-popup'), function(){
      $o=self::opts();
      echo '<label><input type="checkbox" name="'.self::OPTKEY.'[show_once]" value="1" '.checked(1,$o['show_once'],false).'/> '.__('Recordar cierre','eweb-mc-video-popup').'</label> ';
      echo '<input type="number" min="0" step="1" name="'.self::OPTKEY.'[hours]" value="'.esc_attr($o['hours']).'" style="width:80px"/> '.__('horas','eweb-mc-video-popup');
      echo '<p class="description">'.__('Para pruebas puedes forzarlo con ?mcvp=1 en la URL; para suprimir, usa ?mcvp=0','eweb-mc-video-popup').'</p>';
    });

    self::add_field('start', __('Segundo inicial','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<input type="number" min="0" step="1" name="'.self::OPTKEY.'[start]" value="'.esc_attr($o['start']).'" style="width:100px"/>';
    });

    self::add_field('mute', __('Autoplay silenciado','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[mute]" value="1" '.checked(1,$o['mute'],false).'/> '.__('Recomendado para que el autoplay funcione','eweb-mc-video-popup').'</label>';
    });

    self::add_field('controls', __('Mostrar controles','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[controls]" value="1" '.checked(1,$o['controls'],false).'/> '.__('Controles del reproductor YouTube','eweb-mc-video-popup').'</label>';
    });

    self::add_field('open_on_close', __('Abrir destino al cerrar (X)','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[open_on_close]" value="1" '.checked(1,$o['open_on_close'],false).'/> '.__('Abrir en nueva pestaña al pulsar X','eweb-mc-video-popup').'</label>';
    });

    self::add_field('toplink_enabled', __('Capa clicable superior','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[toplink_enabled]" value="1" '.checked(1,$o['toplink_enabled'],false).'/> '.__('Si está activa, al hacer clic sobre el vídeo se abre la URL destino en nueva pestaña (no cierra el popup)','eweb-mc-video-popup').'</label>';
    });

    self::add_field('debug_mode', __('Modo debug','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[debug_mode]" value="1" '.checked(1,$o['debug_mode'],false).'/> '.__('Activar mensajes de debug en consola del navegador','eweb-mc-video-popup').'</label>';
    });

    self::add_field('disable_for_admin', __('Desactivar para administradores','eweb-mc-video-popup'), function(){
      $o=self::opts(); echo '<label><input type="checkbox" name="'.self::OPTKEY.'[disable_for_admin]" value="1" '.checked(1,$o['disable_for_admin'],false).'/> '.__('No mostrar el popup si el usuario actual es un administrador.','eweb-mc-video-popup').'</label>';
    });
  }

  private static function add_field($id, $label, $cb){
    add_settings_field($id, $label, $cb, self::SLUG, 'mcvp_main');
  }

  public static function settings_page(){
    echo '<div class="wrap"><h1>'.esc_html__('MC Video Popup','eweb-mc-video-popup').'</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields(self::SLUG);
    do_settings_sections(self::SLUG);
    submit_button();
    echo '</form>';

    echo '<hr/><p><strong>'.esc_html__('Shortcode opcional:','eweb-mc-video-popup').'</strong> <code>[eweb_mc_video_popup]</code> ';
    echo esc_html__('(usa los valores del admin por defecto; puedes sobreescribir URLs y tiempos por atributos si lo necesitas).','eweb-mc-video-popup');
    echo '</p></div>';
  }
}

MC_Video_Popup::init();
