{contentType javascript}
$(document).ready(function(){
    $('#gameBlock').hover(

    function () {
        $('#gamePuzzle').css("z-index", 10);
        $('#gamePuzzle').animate({
            left: '-25px',
            bottom: '-25px'
        }, 100);

        $({ deg: 0}).animate({ deg: -10}, {
            duration: 100,
            step: function(now) {
                $('#gamePuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    },

    function () {
        $('#gamePuzzle').css("z-index", 5);
        $('#gamePuzzle').animate({
            left: '0px',
            bottom: '0px',
            zIndex: '0'
        }, 100);

        $({ deg: -10}).animate({ deg: 0}, {
            duration: 100,
            step: function(now) {
                $('#gamePuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    }
);

$('#discussionBlock').hover(

    function () {
        $('#discussionPuzzle').css("z-index", 10);
        $('#discussionPuzzle').animate({
            right: '-25px',
            bottom: '-25px'

        }, 100);

        $({ deg: 0}).animate({ deg: 10}, {
            duration: 100,
            step: function(now) {
                $('#discussionPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    },

    function () {
        $('#discussionPuzzle').css("z-index", 5);
        $('#discussionPuzzle').animate({
            right: '0px',
            bottom: '0px',
            zIndex: '0'
        }, 100);

        $({ deg: 10}).animate({ deg: 0}, {
            duration: 100,
            step: function(now) {
                $('#discussionPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    }
);

$('#teamBlock').hover(

    function () {
        $('#teamPuzzle').css("z-index", 10);
        $('#teamPuzzle').animate({
            right: '-25px',
            top: '-25px'

        }, 100);

        $({ deg: 0}).animate({ deg: 10}, {
            duration: 100,
            step: function(now) {
                $('#teamPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    },

    function () {
        $('#teamPuzzle').css("z-index", 5);
        $('#teamPuzzle').animate({
            right: '0px',
            top: '0px',
            zIndex: '0'
        }, 100);

        $({ deg: 10}).animate({ deg: 0}, {
            duration: 100,
            step: function(now) {
                $('#teamPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    }
);

$('#infoBlock').hover(

    function () {
        $('#infoPuzzle').css("z-index", 10);
        $('#infoPuzzle').animate({
            left: '-25px',
            top: '-25px'

        }, 100);

        $({ deg: 0}).animate({ deg: -10}, {
            duration: 100,
            step: function(now) {
                $('#infoPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    },

    function () {
        $('#infoPuzzle').css("z-index", 5);
        $('#infoPuzzle').animate({
            left: '0px',
            top: '0px',
            zIndex: '0'
        }, 100);

        $({ deg: -10}).animate({ deg: 0}, {
            duration: 100,
            step: function(now) {
                $('#infoPuzzle').css({
                    transform: 'rotate(' + now + 'deg)'
                });
            }
        });
    }
);
});