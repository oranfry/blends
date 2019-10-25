(function(){
    var onResize = function() {
        var width = Math.floor(window.innerWidth - 200 - (window.innerWidth >= 800 && 238 || 0));
        var height = Math.floor(window.innerHeight - 200);

        $('#bg-container').css({width: width + 'px', height: height + 'px'});

        var $canvas = $('#bg');

        if (!$canvas.length) {
            return;
        }

        $canvas.attr('width', width).attr('height', height);

        var c = document.getElementById("bg");
        var ctx = c.getContext("2d");
        width = $canvas.width() - 2;
        height = $canvas.height() - 2;

        ctx.lineWidth = 1;
        ctx.lineJoin = 'miter';
        ctx.strokeStyle = "#efefef";

        ctx.beginPath();

        if (typeof divs != 'undefined') {
            for (var i = 0; i < divs.length; i++) {
                ctx.moveTo(width * divs[i] + 1, 0);
                ctx.lineTo(width * divs[i] + 1, height + 1);
                ctx.stroke();
            }
        }

        ctx.strokeStyle = "#bbb";
        ctx.lineWidth = 2;

        ctx.beginPath();
        var xAxis = height * (1 - xAxisProp);

        ctx.moveTo(0, xAxis + 2);
        ctx.lineTo(width + 2, xAxis + 2);

        ctx.moveTo(0, 0);
        ctx.lineTo(width + 2, 0);

        ctx.moveTo(0, height + 2);
        ctx.lineTo(width + 2, height + 2);

        ctx.moveTo(0, 0);
        ctx.lineTo(0, height + 2);

        ctx.moveTo(width + 2, 0);
        ctx.lineTo(width + 2, height + 2);

        ctx.stroke();

        if (today) {
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#' + highlight;
            ctx.beginPath();
            ctx.moveTo(today * width + 1, 1);
            ctx.lineTo(today * width + 1, height + 1);
            ctx.stroke();
        }

        ctx.strokeStyle = "#333333";
        ctx.lineWidth = 1;
        ctx.lineJoin = 'round';
        ctx.beginPath();

        ctx.moveTo(width * points[0][0] + 1, height * (1 - points[0][1]) + 1);

        for (var i = 1; i < points.length; i++) {
            ctx.lineTo(Math.round(width * points[i][0] + 1), Math.round(height * (1 - points[i][1]) + 1));
            ctx.stroke();
        }
    };

    $(window).on('resize', onResize);
    onResize();
})();