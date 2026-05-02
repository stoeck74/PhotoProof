/**
 * PhotoProof — Masonry layout init
 *
 * Instantiates Masonry on the gallery grid when the 'masonry' layout
 * is enabled in settings. Uses a gutter sizer element for percentage-
 * based spacing, so no rounding issues on resize.
 */
jQuery( function ( $ ) {

    var $grid = $( '#pp-grid.pp-layout-masonry' );

    if ( ! $grid.length ) {
        return;
    }

    var msnry = $grid.masonry( {
        itemSelector      : '.pp-card',
        columnWidth       : '.pp-column-sizer',
        gutter            : '.pp-gutter-sizer',
        percentPosition   : true,
        transitionDuration: '0.2s',
    } );

    // Layout once images are loaded (prevents overlap)
    $grid.imagesLoaded().progress( function () {
        msnry.masonry( 'layout' );
    } );

    // Relayout on window resize
    $( window ).on( 'resize.ppMasonry', function () {
        msnry.masonry( 'layout' );
    } );

} );