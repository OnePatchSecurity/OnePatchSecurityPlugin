document.addEventListener(
	'DOMContentLoaded',
	function () {
		const tooltips = document.querySelectorAll( '.tooltip' );

		tooltips.forEach(
			tooltip => {
            tooltip.addEventListener(
                    'focus',
                    function () {
                        this.querySelector( '.tooltip-content' ).style.display = 'block';
                    }
				);
			tooltip.addEventListener(
				'blur',
				function () {
					this.querySelector( '.tooltip-content' ).style.display = 'none';
				}
			);
			}
		);
	}
);
