<?php
/**
 * Schema Generator
 *
 * This class handles the generation of a JSON schema for WordPress block types.
 *
 * @package BoxUk\WpAiContentGeneration
 */

declare(strict_types=1);

namespace BoxUk\WpAiContentGeneration;

/**
 * SchemaGenerator Class
 */
final class SchemaGenerator { 

	/**
	 * Allowed blocks for the schema generation.
	 * 
	 * Todo - can we use Ai to pre-determine which blocks are going to be used, thus reducing the schema to the exact blocks we need?
	 * OpenAI only allows 100 Properties, which makes it difficult to include all blocks.
	 *
	 * @var array<string>
	 */
	const ALLOWED_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/image',
		// 'core/group',
		// 'core/columns',
		// 'core/column',
		'core/media-text',
	);

	const INNER_BLOCK_SUPPORTED_BLOCKS = array(
		// 'core/columns'    => array( 'core/column' ),
		// 'core/group' => array( 'core/paragraph', 'core/heading', 'core/list', 'core/image' ),
		// 'core/column'     => array( 'core/paragraph', 'core/heading', 'core/list', 'core/image' ),
		'core/media-text' => array( 'core/paragraph', 'core/heading', 'core/list' ),
		// 'core/cover'      => array( 'core/paragraph', 'core/heading', 'core/list' ),
	);

	const DISALLOWED_ATTRIBUTES = array(
		'fontFamily', 
		'borderColor',
		'className',
		'gradient',
		'templateLock',
		'height',
		'placeholder',
		'fontSize',
		'dropCap', 
		'direction',
		'align',
		'id',
		'scale',
		'sizeSlug',
	);

	/**
	 * Base JSON schema for the UI structure.
	 *
	 * @var string
	 */
	private const BASE = '
{
	"$schema": "http://json-schema.org/draft-07/schema#",
	"type": "object",
	"title": "UI Schema",
	"version": "1.0.0",
	"description": "A schema for defining a UI structure with blocks, each having a name, attributes, and optional inner blocks.",
	"required": ["blocks"],
	"additionalProperties": false,
	"properties": {}
}
';

	/**
	 * JSON schema definitions for blocks.
	 *
	 * @var array
	 */
	private array $block_definitions = array();

	/**
	 * Get Blocks
	 *
	 * @return array<\WP_Block_Type> Blocks.
	 */
	public static function get_blocks(): array { 
		return array_filter(
			\WP_Block_Type_Registry::get_instance()->get_all_registered(),
			function ( $block ) {
				return in_array( $block->name, self::ALLOWED_BLOCKS, true );
			}
		);
	}

	/**
	 * Generate the JSON schema for WordPress block types.
	 *
	 * @return object
	 */
	public function generate(): object {
		$schema = json_decode( self::BASE, false );

		$blocks = array_map(
			function ( \WP_Block_Type $block ) {
				return $this->from( $block );
			},
			$this->get_blocks()
		);

		$schema->properties = (object) array(
			'blocks' => array(
				'type'  => 'array',
				'items' => array(
					'anyOf' => array_values( $blocks ),
				),
			),
		);
		
		return $schema;
	}

	/**
	 * Convert a WP_Block_Type object to a block schema object.
	 *
	 * @param \WP_Block_Type $block The block type object.
	 * @return object The schema representation of the allowed values for block.
	 */
	private function from( \WP_Block_Type $block ): object {
	   
		$name = $block->name;
		/**
		 * Attribute array
		 * 
		 * @var array<string,{type?:"null"|"boolean"|"object"|"array"|"string"|"integer"|"number",enum?:string[],default?:null|boolean|object|array|string|integer}> $attributes The attributes of the block. 
		 */
		$attributes = $block->attributes;

		$attribute_properties = array();

		foreach ( $attributes as $attr_name => $attr ) {

			if ( in_array( $attr_name, self::DISALLOWED_ATTRIBUTES, true ) ) {
				continue; // Skip disallowed attributes.
			}

			$property = array(
				'type' => match ( $attr['type'] ) {
					'NULL' => 'string',
					'rich-text'  => 'string',
					default   => $attr['type'],
				},
			);

			if ( 'array' === $property['type'] || 'object' === $property['type'] ) {
				if ( empty( $attr['default'] ) ) {
					// If the default is not set, we cannot define an open-ended array or object.
					// OpenAI's API does not support open-ended arrays or objects in the schema.
					continue;
				} elseif ( 'array' === $property['type'] ) {
					$default             = $attr['default'];
					$property['default'] = $default;
					if ( is_array( $default ) && count( $default ) > 0 ) {
						$first             = reset( $default );
						$item_type         = gettype( $first );
						$property['items'] = $this->get_item_type( $item_type );
					}
				} elseif ( 'object' === $property['type'] ) {
					// Infer properties from the default object.
					$default = $attr['default'];
					if ( is_array( $default ) ) {
						$object_properties = array();
						foreach ( $default as $key => $value ) {
							$type                      = gettype( $value );
							$object_properties[ $key ] = $this->get_item_type( $type );
						}
						$property['properties'] = $object_properties;
						$property['required']   = array_keys( $object_properties );
					}
				}
			}

			if ( isset( $attr['enum'] ) ) {
				$property['enum'] = $attr['enum'];
			}

			if ( isset( $attr['default'] ) ) {
				$property['default'] = $attr['default'];
			}

			if ( empty( $property ) ) { 
				continue;
			}

			$attribute_properties[ $attr_name ] = $property;
		}

		$schema = array(
			'type'                 => 'object',
			'title'                => $name,
			'description'          => $block->description,
			'additionalProperties' => false,
			'required'             => array( 'name', 'attributes' ),
			'properties'           => array(
				'name'       => array(
					'type'        => 'string',
					'description' => 'The name of the block.',
					'enum'        => array( $name ),
				),
				'attributes' => array(
					'type'                 => 'object',
					'description'          => 'The attributes of the block.',
					'additionalProperties' => false,
					'properties'           => $attribute_properties,
					'required'             => array_keys( $attribute_properties ),   
				),
			),
		);

		if ( array_search( $name, array_keys( self::INNER_BLOCK_SUPPORTED_BLOCKS ), true ) !== false ) {
			$schema['required'][]                = 'innerBlocks';
			$schema['properties']['innerBlocks'] = array(
				'type'        => 'array',
				'description' => 'An array of inner blocks.',
				'items'       => array(
					'anyOf' => array_values( 
						array_map(
							function ( $inner_block ) {
								return isset( $this->block_definitions[ $inner_block ] ) ? 
									$this->block_definitions[ $inner_block ] :
									$this->from( \WP_Block_Type_Registry::get_instance()->get_registered( $inner_block ) );
							},
							self::INNER_BLOCK_SUPPORTED_BLOCKS[ $name ]
						)
					),
				),
			);
		}

		$this->block_definitions[ $name ] = (object) $schema;

		return (object) $schema;
	}


	/**
	 * Get Item Type
	 * 
	 * @param string $type The type of the item.
	 * 
	 * @return ?array<{type:string}>
	 */
	private function get_item_type( string $type ): ?array {
		if ( 'null' === $type || 'NULL' === $type ) {
			return null;
		}

		return match ( $type ) {
			'double', 'integer' => array( 'type' => 'number' ),
			'rich-text' => array( 'type' => 'string' ),
			default    => array( 'type' => $type ),
		};
	}
}
