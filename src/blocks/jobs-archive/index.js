/**
 * Apprenticeship Jobs Archive — Gutenberg block editor script.
 *
 * Server-side rendered: this file only handles the editor UI
 * (InspectorControls + placeholder preview). Actual output comes
 * from render.php via the block's `render` attribute.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	SelectControl,
	ColorPicker,
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( {
			className: 'apprco-jobs-archive-editor',
		} );

		const {
			perPage,
			columns,
			showSearch,
			showFilters,
			showDistanceFilter,
			showStats,
			showPagination,
			orderBy,
			order,
			filterLevel,
			filterRoute,
			colorPrimary,
			layout,
		} = attributes;

		return (
			<>
				<InspectorControls>
					{ /* ── Layout ─────────────────────────────────────────── */ }
					<PanelBody title={ __( 'Layout', 'apprenticeship-connect' ) } initialOpen={ true }>
						<SelectControl
							label={ __( 'Layout style', 'apprenticeship-connect' ) }
							value={ layout }
							options={ [
								{ label: __( 'Grid', 'apprenticeship-connect' ), value: 'grid' },
								{ label: __( 'List', 'apprenticeship-connect' ), value: 'list' },
							] }
							onChange={ ( v ) => setAttributes( { layout: v } ) }
						/>
						{ layout === 'grid' && (
							<RangeControl
								label={ __( 'Columns', 'apprenticeship-connect' ) }
								value={ columns }
								onChange={ ( v ) => setAttributes( { columns: v } ) }
								min={ 1 }
								max={ 4 }
							/>
						) }
						<RangeControl
							label={ __( 'Vacancies per page', 'apprenticeship-connect' ) }
							value={ perPage }
							onChange={ ( v ) => setAttributes( { perPage: v } ) }
							min={ 4 }
							max={ 100 }
							step={ 4 }
						/>
					</PanelBody>

					{ /* ── Search & Filters ─────────────────────────────────── */ }
					<PanelBody title={ __( 'Search & Filters', 'apprenticeship-connect' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show search bar', 'apprenticeship-connect' ) }
							checked={ showSearch }
							onChange={ ( v ) => setAttributes( { showSearch: v } ) }
						/>
						<ToggleControl
							label={ __( 'Show level / route filters', 'apprenticeship-connect' ) }
							checked={ showFilters }
							onChange={ ( v ) => setAttributes( { showFilters: v } ) }
						/>
						<ToggleControl
							label={ __( 'Show distance / postcode filter', 'apprenticeship-connect' ) }
							checked={ showDistanceFilter }
							onChange={ ( v ) => setAttributes( { showDistanceFilter: v } ) }
						/>
					</PanelBody>

					{ /* ── Sorting & Defaults ────────────────────────────────── */ }
					<PanelBody title={ __( 'Default sort & filters', 'apprenticeship-connect' ) } initialOpen={ false }>
						<SelectControl
							label={ __( 'Default sort by', 'apprenticeship-connect' ) }
							value={ orderBy }
							options={ [
								{ label: __( 'Closing date', 'apprenticeship-connect' ), value: 'closing_date' },
								{ label: __( 'Posted date', 'apprenticeship-connect' ), value: 'posted_date' },
								{ label: __( 'Employer name', 'apprenticeship-connect' ), value: 'employer_name' },
								{ label: __( 'Title', 'apprenticeship-connect' ), value: 'title' },
							] }
							onChange={ ( v ) => setAttributes( { orderBy: v } ) }
						/>
						<SelectControl
							label={ __( 'Sort order', 'apprenticeship-connect' ) }
							value={ order }
							options={ [
								{ label: __( 'Ascending', 'apprenticeship-connect' ), value: 'ASC' },
								{ label: __( 'Descending', 'apprenticeship-connect' ), value: 'DESC' },
							] }
							onChange={ ( v ) => setAttributes( { order: v } ) }
						/>
					</PanelBody>

					{ /* ── Display ──────────────────────────────────────────── */ }
					<PanelBody title={ __( 'Display', 'apprenticeship-connect' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show result stats', 'apprenticeship-connect' ) }
							checked={ showStats }
							onChange={ ( v ) => setAttributes( { showStats: v } ) }
						/>
						<ToggleControl
							label={ __( 'Show pagination', 'apprenticeship-connect' ) }
							checked={ showPagination }
							onChange={ ( v ) => setAttributes( { showPagination: v } ) }
						/>
					</PanelBody>

					{ /* ── Style ────────────────────────────────────────────── */ }
					<PanelBody title={ __( 'Primary colour', 'apprenticeship-connect' ) } initialOpen={ false }>
						<Text variant="muted">
							{ __( 'Used for buttons, badges, and links.', 'apprenticeship-connect' ) }
						</Text>
						<ColorPicker
							color={ colorPrimary }
							onChange={ ( v ) => setAttributes( { colorPrimary: v } ) }
							enableAlpha={ false }
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
} );
