/**
 * Stats Widget Component
 *
 * Displays a single stat with icon and value.
 *
 * @package ApprenticeshipConnect
 */

import { Card, CardBody, Flex, FlexItem, FlexBlock, __experimentalText as Text } from '@wordpress/components';
import { Icon } from '@wordpress/icons';

/**
 * StatsWidget Component
 *
 * @param {Object} props                Component props.
 * @param {string} props.title          Widget title.
 * @param {string|number} props.value   Widget value.
 * @param {Object} props.icon           WordPress icon.
 * @param {string} props.color          Color theme (blue, green, purple, red, orange).
 * @param {boolean} props.isDate        Whether value is a date string.
 */
const StatsWidget = ({ title, value, icon, color = 'blue', isDate = false }) => {
	const colorClasses = {
		blue: 'apprco-stat-blue',
		green: 'apprco-stat-green',
		purple: 'apprco-stat-purple',
		red: 'apprco-stat-red',
		orange: 'apprco-stat-orange',
	};

	return (
		<Card className={`apprco-stat-widget ${colorClasses[color]}`}>
			<CardBody>
				<Flex align="center" justify="space-between">
					<FlexBlock>
						<Text variant="muted" size="small" className="apprco-stat-title">
							{title}
						</Text>
						<div className="apprco-stat-value">{isDate ? formatDate(value) : value}</div>
					</FlexBlock>
					<FlexItem>
						<div className="apprco-stat-icon">
							<Icon icon={icon} size={32} />
						</div>
					</FlexItem>
				</Flex>
			</CardBody>
		</Card>
	);
};

/**
 * Format date string
 *
 * @param {string} dateString Date string.
 * @return {string} Formatted date.
 */
function formatDate(dateString) {
	if (!dateString || dateString === 'Never') {
		return dateString;
	}

	try {
		const date = new Date(dateString);
		return date.toLocaleDateString(undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		});
	} catch {
		return dateString;
	}
}

export default StatsWidget;
