/**
 * Panel w edytorze blokow: pole wyboru "grafika z AI" dla obrazka wyroznionego.
 *
 * Flaga zapisywana jest na ZALACZNIKU, nie na wpisie, wiec zapisujemy ja od razu
 * przez REST, a nie razem z wpisem. Dzieki temu raz oznaczony plik jest podpisany
 * wszedzie, gdzie go uzyjesz.
 *
 * Bez JSX i bez kroku budowania - czysty wp.element.createElement.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var CheckboxControl = wp.components.CheckboxControl;
	var Spinner = wp.components.Spinner;
	var useSelect = wp.data.useSelect;
	var apiFetch = wp.apiFetch;

	function Panel() {

		var idObrazka = useSelect( function ( select ) {
			var edytor = select( 'core/editor' );
			return edytor ? edytor.getEditedPostAttribute( 'featured_media' ) : 0;
		}, [] );

		var stanZaznaczenia = useState( null );
		var zaznaczone = stanZaznaczenia[ 0 ];
		var ustawZaznaczone = stanZaznaczenia[ 1 ];

		var stanZapisu = useState( false );
		var zapisuje = stanZapisu[ 0 ];
		var ustawZapisuje = stanZapisu[ 1 ];

		useEffect( function () {

			if ( ! idObrazka ) {
				ustawZaznaczone( null );
				return;
			}

			apiFetch( { path: '/wp/v2/media/' + idObrazka + '?context=edit' } )
				.then( function ( media ) {
					ustawZaznaczone( !! ( media && media.meta && media.meta._wd_ai_grafika ) );
				} )
				.catch( function () {
					ustawZaznaczone( false );
				} );

		}, [ idObrazka ] );

		function zmien( nowa ) {

			ustawZaznaczone( nowa );
			ustawZapisuje( true );

			apiFetch( {
				path: '/wp/v2/media/' + idObrazka,
				method: 'POST',
				data: { meta: { _wd_ai_grafika: nowa } }
			} )
				.catch( function () {
					/* Cofamy, zeby pole nie klamalo o stanie zapisu. */
					ustawZaznaczone( ! nowa );
				} )
				.finally( function () {
					ustawZapisuje( false );
				} );
		}

		var tresc;

		if ( ! idObrazka ) {
			tresc = el( 'p', { style: { opacity: 0.7, margin: 0 } },
				'Najpierw ustaw obrazek wyrozniajacy.' );
		} else if ( null === zaznaczone ) {
			tresc = el( Spinner, null );
		} else {
			tresc = el( wp.element.Fragment, null,
				el( CheckboxControl, {
					label: 'Grafika wygenerowana przez AI',
					help: 'Pod obrazkiem pojawi sie podpis informujacy odbiorce. Zapisuje sie od razu, razem z plikiem.',
					checked: zaznaczone,
					disabled: zapisuje,
					onChange: zmien
				} ),
				zapisuje ? el( 'p', { style: { opacity: 0.7, margin: 0, fontSize: '12px' } }, 'Zapisuje...' ) : null
			);
		}

		return el( PluginDocumentSettingPanel, {
			name: 'wd-ai-oznaczenia',
			title: 'Oznaczenie AI',
			className: 'wd-ai-panel'
		}, tresc );
	}

	wp.plugins.registerPlugin( 'wd-ai-oznaczenia', { render: Panel, icon: null } );

} )( window.wp );
