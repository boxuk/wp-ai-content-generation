<?php
/**
 * Model Class
 *
 * This class handles the data for AI content generation, including content, analysis, components, and UI.
 *
 * @package BoxUk\WpAiContentGeneration
 */

declare(strict_types=1);

namespace BoxUk\WpAiContentGeneration;

/**
 * Model Class
 */
class Model { 

	/**
	 * A place to store the prompt/content from the user.
	 *
	 * @var string|null
	 */
	private ?string $content;

	/**
	 * A place to store the content analysis from the AI service.
	 *
	 * @var object|null
	 */
	private ?object $content_analysis;

	/**
	 * A place to store the intent analysis from the AI service.
	 *
	 * @var object|null
	 */
	private ?object $intent_analysis;

	/**
	 * A place to store the UI components selected by the AI service.
	 *
	 * @var array|null
	 */
	private ?array $components;

	private const PREFIX = 'wp_ai_cg_data_';
	private const MODEL  = 'gpt-4o-mini';

	/**
	 * Constructor
	 *
	 * @param string         $id     Unique Identifier for the Data Instance.
	 * @param \OpenAI\Client $client OpenAI Client Instance.
	 */
	public function __construct(
		private string $id,
		private \OpenAI\Client $client
	) { 
		// phpcs:disable Universal.Operators.DisallowShortTernary.Found
		$this->content          = get_transient( self::PREFIX . $this->id . '_content' ) ?: null;
		$this->content_analysis = get_transient( self::PREFIX . $this->id . '_content_analysis' ) ?: null;
		$this->intent_analysis  = get_transient( self::PREFIX . $this->id . '_intent_analysis' ) ?: null;
		$this->components       = get_transient( self::PREFIX . $this->id . '_components' ) ?: null;
		// phpcs:enable Universal.Operators.DisallowShortTernary.Found
	}

	/**
	 * Schedule Task
	 * 
	 * @param string $hook The action hook to schedule.
	 * 
	 * @return void
	 */
	public function schedule( string $hook ): void {
		as_schedule_single_action(
			time(),
			$hook,
			array( $this->id ),
			'wp-ai-content-generation-' . $this->id,
			true
		);
	}

	/**
	 * Get Status
	 */
	public function get_status(): string {

		$tasks = as_get_scheduled_actions(
			array(
				'status' => 'failed',
				'args'   => array( 'id' => $this->id ),
			)
		);

		if ( ! empty( $tasks ) ) {
			return 'error';
		}

		if ( empty( $this->content ) ) {
			return 'pending';
		}
		if ( empty( $this->content_analysis ) ) { 
			$this->schedule( Api::ANALYSE_CONTENT_HOOK );
			return 'content_analysis_pending';
		}
		if ( empty( $this->intent_analysis ) ) {
			$this->schedule( Api::ANALYSE_INTENT_HOOK );
			return 'intent_analysis_pending';
		}
		if ( empty( $this->components ) ) {
			$this->schedule( Api::SELECT_COMPONENTS_HOOK );
			return 'components_pending';
		}
		if ( ! empty( $this->components ) ) {
			return 'complete';
		}
		return 'unknown';
	}

	/**
	 * Get Error Message
	 * 
	 * @todo - Implement error handling and return appropriate error messages.
	 * 
	 * @return string|null
	 */
	public function get_error_message(): ?string {
		return 'An error occurred during content generation. Please try again later.';
	}

	/**
	 * Get UI components
	 *
	 * @return array|null
	 */
	public function get_components(): ?array {
		return $this->components;
	}

	/**
	 * Set Content
	 * 
	 * @param string $content The content to set.
	 */
	public function set_content( string $content ): void {
		$this->content = $content;
		$this->save_data();
	}

	/**
	 * Analyse Content
	 *
	 * @return void
	 */
	public function analyse_content(): void { 
		$response = $this->ai_request(
			array(
				array(
					'role'    => 'system',
					'content' => 'You are a UI analysis assistant. You need to analyze the provided content structure and 
					extract key information that will help determine what UI components should be used.
		  
		  Analyze the following aspects:
		  1. Content type and structure
		  2. Main themes and focus areas
		  3. Content hierarchy
		  4. Key data points that should be highlighted
		  5. Visual elements available
		  
		  Provide a structured analysis as JSON with these categories.',
				),
				array(
					'role'    => 'user',
					'content' => 'Analyze this content:' . $this->content,
				),
			) 
		);

		$this->content_analysis = $this->get_content_from_response( $response );
		$this->save_data();
	}

	/**
	 * Analyse Intent
	 *
	 * @return void
	 */
	public function analyse_intent(): void {
		$response = $this->ai_request(
			array(
				array(
					'role'    => 'system',
					'content' => "You are a UI planning assistant. You need to analyze the user's intent and determine how 
                    it should influence the UI layout and component selection.
          
          Consider:
          1. Content priorities based on intent
          2. Components that best serve this intent
          3. Information hierarchy appropriate for the intent
          4. Content sections to emphasize or de-emphasize
          
          Provide a structured analysis as JSON with these categories.",
				),
				array(
					'role'    => 'user',
					'content' => 'Content Analysis:' . $this->content . "\nContent Analysis: " . wp_json_encode( $this->content_analysis ),
				),
			),
		);

		$this->intent_analysis = $this->get_content_from_response( $response );
		$this->save_data();
	}

	/**
	 * Generate Components
	 *
	 * @return void
	 * 
	 * @throws \Exception If the response from the AI service is invalid or the components format is incorrect.
	 */
	public function generate_components(): void {
		$schema   = ( new SchemaGenerator() )->generate();
		$messages = array(
			array(
				'role'    => 'system',
				'content' => "You are a UI component selection assistant. Based on the content analysis and user intent 
                    analysis, select appropriate UI components from the available component library.
          
          Your output should be a structured list of components to use for different sections of the content, following 
          a logical layout that serves the user's intent.
          Component Library: " . wp_json_encode( $this->get_available_components(), JSON_PRETTY_PRINT ) . '

          Return a JSON array of selected components with their placement in the UI.',
			),
			array(
				'role'    => 'user',
				'content' => 'Content Analysis: ' . wp_json_encode( $this->content_analysis ) . "\nIntent: " . wp_json_encode( $this->intent_analysis ) . "\nPrompt: " . $this->content,
			),
		);
		$response = $this->ai_request( $messages, $schema );

		$components = $this->get_content_from_response( $response );

		if ( isset( $components->blocks ) && is_array( $components->blocks ) ) {
			$this->components = $components->blocks;
			$this->save_data();
		} else {
			throw new \Exception( 'Invalid components format received from AI service.' );
		}
	}

	/**
	 * Save the data to transients.
	 */
	public function save_data(): void {
		set_transient( self::PREFIX . $this->id . '_content', $this->content, 12 * HOUR_IN_SECONDS );
		set_transient( self::PREFIX . $this->id . '_content_analysis', $this->content_analysis, 12 * HOUR_IN_SECONDS );
		set_transient( self::PREFIX . $this->id . '_intent_analysis', $this->intent_analysis, 12 * HOUR_IN_SECONDS );
		set_transient( self::PREFIX . $this->id . '_components', $this->components, 12 * HOUR_IN_SECONDS );
		if ( class_exists( 'WP_CLI' ) ) { 
			// if we're running in WP-CLI, we don't need to update the status.
			return;
		}
		$this->get_status(); // Update the status based on the current state of the model.
	}

	/**
	 * Get the object from the AI response.
	 *
	 * @param \OpenAI\Responses\Chat\CreateResponse $response The response from the AI service.
	 * 
	 * @return object|array<object> An object containing the content from the AI response.
	 * 
	 * @throws \Exception If the response is invalid or the content cannot be parsed.
	 */
	private function get_content_from_response( \OpenAI\Responses\Chat\CreateResponse $response ): object {
		if ( ! $response || ! isset( $response->choices[0]->message->content ) ) {
			throw new \Exception( 'Invalid response from AI service.' );
		}
		$content = json_decode( $response->choices[0]->message->content );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new \Exception( wp_kses_post( json_last_error_msg() ) );
		}

		return $content;
	}

	/**
	 * Make a request to the AI service with the provided messages.
	 *
	 * @param array       $messages The messages to send to the AI service.
	 * @param object|null $schema   Optional JSON schema for the response format.
	 * 
	 * @return \OpenAI\Responses\Chat\CreateResponse
	 */
	private function ai_request( array $messages, ?object $schema = null ): \OpenAI\Responses\Chat\CreateResponse { 

		$response_format = $schema ? array(
			'type'        => 'json_schema',
			'json_schema' => array( 
				'name'   => 'UI-Components', 
				'schema' => $schema,
				'strict' => true,
			),
		) : array(
			'type' => 'json_object',
		);

		$request = array(
			'model'           => self::MODEL,
			'response_format' => $response_format,
			'messages'        => $messages,
		);

		return $this->client->chat()->create( $request );
	}

	/**
	 * Get the available components (blocks) in WordPress.
	 *
	 * @return array{
	 *     name: string,
	 *     description: string,
	 *     attributes: array<string, mixed>
	 * }
	 */
	private function get_available_components(): array {
		return array_map(
			function ( \WP_Block_Type $block ) {
				return array(
					'name'        => $block->name,
					'description' => $block->description,
					'attributes'  => $block->attributes,
				);
			},
			SchemaGenerator::get_blocks()
		);
	}
}
