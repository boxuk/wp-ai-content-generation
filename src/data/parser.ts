import { createBlock } from '@wordpress/blocks';
import type { ComponentParser } from './types';

/**
 * Isolating this out since we might want to expand the way we
 * convert components from the AI to blocks if we need to do
 * anything special.
 * @param component
 */
export const parse: ComponentParser = ( component ) =>
	createBlock(
		component.name,
		component.attributes,
		component.innerBlocks?.map( parse )
	);
