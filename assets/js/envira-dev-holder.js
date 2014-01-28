// Needed functions.
function enviraGetColWidth(container, gutter) {
    var width,
        windowWidth = $(window).width(),
        numColumns = container.data('envira-columns');

    switch(numColumns){
        case 1 :
            width = container.width();
        break;
        case 2 :
            if ( windowWidth <= 480 ) {
                width = container.width();
            } else {
                width = container.width() / 2;
            }
        break;
        case 3 :
            if ( windowWidth <= 480 ) {
                width = container.width();
            } else if ( windowWidth <= 768 ) {
                width = container.width() / 2;
            } else {
                width = container.width() / 3;
            }
        case 4 :
        case 5 :
        case 6 :
            if ( windowWidth <= 480 ) {
                width = container.width();
            } else if ( windowWidth <= 768 ) {
                width = container.width() / 2;
            } else if ( windowWidth <= 1024 ) {
                width = container.width() / 3;
            } else {
                width = container.width() / numColumns;
            }
        break;
    }

    return parseInt(width - (gutter*(numColumns-1))/numColumns);
}

function enviraSetWidths(container, gutter) {
    var colWidth = enviraGetColWidth(container, gutter);
    container.children().css({ width: colWidth });
}

// Custom Isotope extensions.
$.Isotope.prototype._getMasonryGutterColumns = function() {
    var gutter = this.options.masonry && this.options.masonry.gutterWidth || 0;
    containerWidth = this.element.width();

    this.masonry.columnWidth = this.options.masonry && this.options.masonry.columnWidth ||
    // Or use the size of the first item
    this.$filteredAtoms.outerWidth(true) ||
    // If there's no items, use size of container
    containerWidth;

    this.masonry.columnWidth += gutter;

    this.masonry.cols = Math.floor((containerWidth + gutter) / this.masonry.columnWidth);
    this.masonry.cols = Math.max(this.masonry.cols, 1);
};

$.Isotope.prototype._masonryReset = function() {
    // Layout-specific props
    this.masonry = {};
    // FIXME shouldn't have to call this again
    this._getMasonryGutterColumns();
    var i = this.masonry.cols;
    this.masonry.colYs = [];
    while (i--) {
        this.masonry.colYs.push(0);
    }
};

$.Isotope.prototype._masonryResizeChanged = function() {
    var prevSegments = this.masonry.cols;
    // Update cols/rows
    this._getMasonryGutterColumns();
    // Return if updated cols/rows is not equal to previous
    return (this.masonry.cols !== prevSegments);
};

function enviraThrottle(func, wait) {
    return function() {
        var that = this,
            args = [].slice(arguments);

        clearTimeout(func._throttleTimeout);

        func._throttleTimeout = setTimeout(function() {
            func.apply(that, args);
        }, wait);
    };
}