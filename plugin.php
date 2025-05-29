<?php
/**
 * Plugin Name:       WordPress AI Content Generation
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            James Amner<jdamner@me.com>
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ai-content-generation
 * 
 * @package BoxUk\WpAiContentGeneration
 */

use BoxUk\WpAiContentGeneration\Model;
use BoxUk\WpAiContentGeneration\SchemaGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load Deps.
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-assets.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-model.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-schemagenerator.php';

// Load environment variables.
$dotenv = \Dotenv\Dotenv::createImmutable( __DIR__ );
$dotenv->load();
$dotenv->required( 'OPENAI_API_KEY' )->notEmpty();

// Initialize the plugin..
$open_ai = \OpenAI::client( $_ENV['OPENAI_API_KEY'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
( new \BoxUk\WpAiContentGeneration\Api( $open_ai ) )->init(); 
( new \BoxUk\WpAiContentGeneration\Assets() )->init();

// add_action(
//  'wp_head',
//  function () { 
//      $sg     = new SchemaGenerator(); 
//      $schema = $sg->generate();
//      echo '<pre>' . esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT ) ) . '</pre>';
//      exit;
//  }
// );

if ( class_exists( 'WP_CLI' ) ) { 
	\WP_CLI::add_command(
		'wp-ai-content-generation',
		function ( $args ) use ( $open_ai ) {
			$model = new Model( uniqid(), $open_ai );
			$model->set_content( $args[0] ?? 'Generate a blog post about the benefits of AI in content generation.' );
			$model->analyse_content();
			$model->analyse_intent();
			$model->generate_components();
			\WP_CLI::log(
				var_export( $model->get_components(), true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);
		}
	);
}
