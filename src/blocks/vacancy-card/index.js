/**
 * Vacancy Card block – editor registration.
 *
 * Renders a single appcon_vacancy post as a card.  Works standalone (postId
 * attribute) or inside the core Query Loop block (picks up `postId` context).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	Spinner,
	Placeholder,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: function Edit( { attributes, setAttributes, context } ) {
		const blockProps = useBlockProps( {
			className: 'appcon-block-vacancy-card-editor',
		} );

		const {
			showEmployer,
			showLocation,
			showLevel,
			showClosingDate,
			showWage,
			showExcerpt,
			linkToPost,
		} = attributes;

		// Resolve postId from attribute or Query Loop context.
		const resolvedPostId = context?.postId || attributes.postId;

		const toggleControls = [
			{ key: 'showEmployer',    label: __( 'Show employer',      'apprenticeship-connector' ) },
			{ key: 'showLocation',    label: __( 'Show location',      'apprenticeship-connector' ) },
			{ key: 'showLevel',       label: __( 'Show level',         'apprenticeship-connector' ) },
			{ key: 'showClosingDate', label: __( 'Show closing date',  'apprenticeship-connector' ) },
			{ key: 'showWage',        label: __( 'Show wage',          'apprenticeship-connector' ) },
			{ key: 'showExcerpt',     label: __( 'Show excerpt',       'apprenticeship-connector' ) },
			{ key: 'linkToPost',      label: __( 'Link card to post',  'apprenticeship-connector' ) },
		];

		if ( ! resolvedPostId ) {
			return (
				<div { ...blockProps }>
					<Placeholder
						icon="id-alt"
						label={ __( 'Vacancy Card', 'apprenticeship-connector' ) }
						instructions={ __( 'Add this block inside a Query Loop to display a vacancy card for each result.', 'apprenticeship-connector' ) }
					/>
				</div>
			);
		}

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Card Fields', 'apprenticeship-connector' ) } initialOpen>
						{ toggleControls.map( ( { key, label } ) => (
							<ToggleControl
								key={ key }
								label={ label }
								checked={ attributes[ key ] }
								onChange={ ( v ) => setAttributes( { [ key ]: v } ) }
							/>
						) ) }
					</PanelBody>
				</InspectorControls>

				<div { ...blockProps }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ { ...attributes, postId: resolvedPostId } }
					/>
				</div>
			</>
		);
	},

	save: () => null,
} );
