/**
 * Vacancy Listing block – editor registration.
 *
 * This block is server-side rendered; the editor shows a live preview via
 * ServerSideRender and an InspectorControls sidebar for attribute editing.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: function Edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( {
			className: 'appcon-block-vacancy-listing-editor',
		} );

		const {
			postsPerPage,
			orderBy,
			order,
			filterLevel,
			filterRoute,
			showExpired,
			layout,
			showPagination,
			showSearch,
		} = attributes;

		// Fetch taxonomy terms for filter dropdowns.
		const levels = useSelect( ( select ) => {
			return select( 'core' ).getEntityRecords( 'taxonomy', 'appcon_level', { per_page: 100 } ) ?? [];
		}, [] );

		const routes = useSelect( ( select ) => {
			return select( 'core' ).getEntityRecords( 'taxonomy', 'appcon_route', { per_page: 100 } ) ?? [];
		}, [] );

		const levelOptions = [
			{ label: __( '— All Levels —', 'apprenticeship-connector' ), value: '' },
			...levels.map( ( t ) => ( { label: t.name, value: String( t.id ) } ) ),
		];

		const routeOptions = [
			{ label: __( '— All Routes —', 'apprenticeship-connector' ), value: '' },
			...routes.map( ( t ) => ( { label: t.name, value: String( t.id ) } ) ),
		];

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Query', 'apprenticeship-connector' ) } initialOpen>
						<RangeControl
							label={ __( 'Vacancies per page', 'apprenticeship-connector' ) }
							value={ postsPerPage }
							onChange={ ( v ) => setAttributes( { postsPerPage: v } ) }
							min={ 1 }
							max={ 50 }
						/>
						<SelectControl
							label={ __( 'Order by', 'apprenticeship-connector' ) }
							value={ orderBy }
							options={ [
								{ label: __( 'Date', 'apprenticeship-connector' ),         value: 'date' },
								{ label: __( 'Title', 'apprenticeship-connector' ),        value: 'title' },
								{ label: __( 'Closing Date', 'apprenticeship-connector' ), value: 'closing_date' },
							] }
							onChange={ ( v ) => setAttributes( { orderBy: v } ) }
						/>
						<SelectControl
							label={ __( 'Order', 'apprenticeship-connector' ) }
							value={ order }
							options={ [
								{ label: __( 'Newest first', 'apprenticeship-connector' ), value: 'DESC' },
								{ label: __( 'Oldest first', 'apprenticeship-connector' ), value: 'ASC' },
							] }
							onChange={ ( v ) => setAttributes( { order: v } ) }
						/>
					</PanelBody>

					<PanelBody title={ __( 'Filters', 'apprenticeship-connector' ) } initialOpen={ false }>
						<SelectControl
							label={ __( 'Level', 'apprenticeship-connector' ) }
							value={ filterLevel }
							options={ levelOptions }
							onChange={ ( v ) => setAttributes( { filterLevel: v } ) }
						/>
						<SelectControl
							label={ __( 'Route', 'apprenticeship-connector' ) }
							value={ filterRoute }
							options={ routeOptions }
							onChange={ ( v ) => setAttributes( { filterRoute: v } ) }
						/>
						<ToggleControl
							label={ __( 'Include expired vacancies', 'apprenticeship-connector' ) }
							checked={ showExpired }
							onChange={ ( v ) => setAttributes( { showExpired: v } ) }
						/>
					</PanelBody>

					<PanelBody title={ __( 'Layout', 'apprenticeship-connector' ) } initialOpen={ false }>
						<SelectControl
							label={ __( 'Layout style', 'apprenticeship-connector' ) }
							value={ layout }
							options={ [
								{ label: __( 'List',  'apprenticeship-connector' ), value: 'list' },
								{ label: __( 'Grid',  'apprenticeship-connector' ), value: 'grid' },
								{ label: __( 'Table', 'apprenticeship-connector' ), value: 'table' },
							] }
							onChange={ ( v ) => setAttributes( { layout: v } ) }
						/>
						<ToggleControl
							label={ __( 'Show search bar', 'apprenticeship-connector' ) }
							checked={ showSearch }
							onChange={ ( v ) => setAttributes( { showSearch: v } ) }
						/>
						<ToggleControl
							label={ __( 'Show pagination', 'apprenticeship-connector' ) }
							checked={ showPagination }
							onChange={ ( v ) => setAttributes( { showPagination: v } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div { ...blockProps }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},

	// No save – fully server-side rendered.
	save: () => null,
} );
