var elem = document.getElementById("bar");
var width = 1;
var id = setInterval(frame, 100);
function frame() {
    if (width >= 100) {
        clearInterval(id);
    } else {
        width++;
        elem.style.width = width + '%';
        document.getElementById("value").innerHTML = width  + '%';
    }
}