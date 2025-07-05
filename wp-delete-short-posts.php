<?php
/*
* Plugin Name: WP Delete Short Posts
* Plugin URI: https://kevin-benabdelhak.fr/plugins/wp-delete-short-posts/
* Description: WP Delete Short Posts est un plugin WordPress qui supprime tous vos articles ayant un nombre de mots inférieur à une valeur spécifiée. Utilisez l'interface d'options pour définir ce nombre de mots et supprimer facilement les articles.
* Version: 1.2
* Author: Kevin BENABDELHAK
* Author URI: https://kevin-benabdelhak.fr
* Contributors: kevinbenabdelhak
*/

if (!defined('ABSPATH')) {
    exit; 
}




if ( !class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
}
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$monUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kevinbenabdelhak/wp-delete-short-posts/', 
    __FILE__,
    'wp-delete-short-posts' 
);

$monUpdateChecker->setBranch('main');








add_action('admin_menu', 'wdsp_add_admin_menu');
add_action('admin_init', 'wdsp_settings_init');

// Fonction pour ajouter une page d'options
function wdsp_add_admin_menu() {
    add_options_page('WP Delete Short Posts', 'WP Delete Short Posts', 'manage_options', 'wp_delete_short_posts', 'wdsp_options_page');
}

// Page d'options du plugin
function wdsp_options_page() {
    if (isset($_POST['wdsp_save_settings'])) {
        // Obtenons les nouvelles options de la requête POST
        $new_settings = isset($_POST['wdsp_settings']) ? $_POST['wdsp_settings'] : array();

        // Si les cases à cocher ne sont pas définies, définissez-les sur 0
        $new_settings['enable_redirect_301'] = isset($new_settings['enable_redirect_301']) ? 1 : 0;
        $new_settings['enable_redirect_410'] = isset($new_settings['enable_redirect_410']) ? 1 : 0;

        // Enregistrons les nouvelles options
        update_option('wdsp_settings', $new_settings);
        echo '<div class="updated"><p>Paramètres enregistrés.</p></div>';
    }

    $options = get_option('wdsp_settings'); // Récupération des options actuelles après l'enregistrement

    ?>
    <div class="wrap">
        <h1>WP Delete Short Posts</h1>
        <form method="post" action="">
            <?php
            settings_fields('wdsp_pluginPage');
            do_settings_sections('wdsp_pluginPage');
            ?>

            <label for="wdsp_number_of_words">Nombre de mots maximum :</label>
            <input type="number" name="wdsp_settings[wdsp_number_of_words]" value="<?php echo esc_attr(isset($options['wdsp_number_of_words']) ? $options['wdsp_number_of_words'] : 150); ?>" />
            <br><br>

            <input type="checkbox" name="wdsp_settings[enable_redirect_301]" <?php checked(isset($options['enable_redirect_301']) ? $options['enable_redirect_301'] : 0, 1); ?> value="1" />
            <label for="wdsp_enable_redirect_301">Activer les redirections 301</label>
            <br>
            <label for="redirect_urls_301">URLs à rediriger vers la page d'accueil (une par ligne) :</label><br>
            <textarea name="wdsp_settings[redirect_urls_301]" rows="5" cols="40"><?php echo esc_textarea(isset($options['redirect_urls_301']) ? $options['redirect_urls_301'] : ''); ?></textarea>
            <br><br>

            <input type="checkbox" name="wdsp_settings[enable_redirect_410]" <?php checked(isset($options['enable_redirect_410']) ? $options['enable_redirect_410'] : 0, 1); ?> value="1" />
            <label for="wdsp_enable_redirect_410">Activer les 410</label>
            <br>
            <label for="redirect_urls_410">URLs à supprimer (une par ligne) :</label><br>
            <textarea name="wdsp_settings[redirect_urls_410]" rows="5" cols="40"><?php echo esc_textarea(isset($options['redirect_urls_410']) ? $options['redirect_urls_410'] : ''); ?></textarea>
            <br><br>

            <button type="submit" name="wdsp_save_settings" class="button">Enregistrer</button>
            <button type="submit" name="wdsp_delete_posts" class="button button-danger">Supprimer les articles</button>
        </form>

        <?php
        // Gestion de la suppression des articles
        if (isset($_POST['wdsp_delete_posts'])) {
            $max_words = isset($options['wdsp_number_of_words']) ? intval($options['wdsp_number_of_words']) : 150;
            $deleted_count = wdsp_delete_short_posts($max_words, $options);
            echo '<div class="updated"><p>' . $deleted_count . ' article(s) supprimé(s).</p></div>';
        }
        ?>
    </div>
    <?php
}

function wdsp_settings_init() {
    if (!get_option('wdsp_settings')) {
        add_option('wdsp_settings', ['wdsp_number_of_words' => 150, 'redirect_urls_301' => '', 'redirect_urls_410' => '', 'enable_redirect_301' => 0, 'enable_redirect_410' => 0]);
    }
}

function wdsp_delete_short_posts($max_words, $options) {
    $query = new WP_Query(array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));

    $deleted_count = 0;

    $redirect_urls_301 = isset($options['enable_redirect_301']) ? explode("\n", trim($options['redirect_urls_301'])) : [];
    $redirect_urls_410 = isset($options['enable_redirect_410']) ? explode("\n", trim($options['redirect_urls_410'])) : [];

    foreach ($query->posts as $post_id) {
        $post_content = get_post($post_id)->post_content;
        $word_count = str_word_count(strip_tags($post_content));

        if ($word_count < $max_words) {
            if ($options['enable_redirect_301']) {
                $redirect_urls_301[] = get_permalink($post_id);
            }
            if ($options['enable_redirect_410']) {
                $redirect_urls_410[] = get_permalink($post_id);
            }
            wp_delete_post($post_id, true);
            $deleted_count++;
        }
    }

    if ($options['enable_redirect_301']) {
        $options['redirect_urls_301'] = implode("\n", array_unique(array_filter($redirect_urls_301)));
    }

    if ($options['enable_redirect_410']) {
        $options['redirect_urls_410'] = implode("\n", array_unique(array_filter($redirect_urls_410)));
    }

    update_option('wdsp_settings', $options);

    return $deleted_count;
}

add_action('template_redirect', 'wdsp_check_redirects');

function wdsp_check_redirects() {
    global $wp;

    $options = get_option('wdsp_settings');

    if (isset($options['enable_redirect_301']) && $options['enable_redirect_301'] == 1) {
        $redirect_urls_301 = isset($options['redirect_urls_301']) ? explode("\n", trim($options['redirect_urls_301'])) : [];
        $current_url = home_url($wp->request);

        foreach ($redirect_urls_301 as $redirect_url) {
            $redirect_url = trim($redirect_url);
            if (rtrim($redirect_url, '/') === rtrim($current_url, '/')) {
                wp_redirect(home_url(), 301);
                exit;
            }
        }
    }

    if (isset($options['enable_redirect_410']) && $options['enable_redirect_410'] == 1) {
        $redirect_urls_410 = isset($options['redirect_urls_410']) ? explode("\n", trim($options['redirect_urls_410'])) : [];
        $current_url = home_url($wp->request);

        foreach ($redirect_urls_410 as $redirect_url) {
            $redirect_url = trim($redirect_url);
            if (rtrim($redirect_url, '/') === rtrim($current_url, '/')) {
                status_header(410);
                echo '<h1>410 Gone</h1>';
                echo '<p>Ce contenu a été supprimé de manière permanente.</p>';
                exit;
            }
        }
    }
}