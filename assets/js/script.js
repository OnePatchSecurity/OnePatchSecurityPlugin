document.addEventListener('DOMContentLoaded', function () {
	const tooltips = document.querySelectorAll('.tooltip');

	tooltips.forEach(tooltip => {
		const content = tooltip.querySelector('.tooltip-content');
		if (!content) return;

		const showTooltip = () => {
			content.hidden = false;
			tooltip.setAttribute('aria-expanded', 'true');
			content.classList.add('visible');
		};

		const hideTooltip = () => {
			content.hidden = true;
			tooltip.setAttribute('aria-expanded', 'false');
			content.classList.remove('visible');
		};

		tooltip.addEventListener('focus', showTooltip);
		tooltip.addEventListener('blur', hideTooltip);
		tooltip.addEventListener('mouseenter', showTooltip);
		tooltip.addEventListener('mouseleave', hideTooltip);
	});
});
