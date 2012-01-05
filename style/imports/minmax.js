// minmax.js - written by Andrew Clover <and@doxdesk.com>
// Adapted for PunBB by Rickard Andersson and Paul Sullivan

/*@cc_on
@if (@_win32 && @_jscript_version>4)

var minmax_elements;

function minmax_bind(el) {
	var em, ms;
	var st= el.style, cs= el.currentStyle;

	if (minmax_elements==window.undefined) {
		if (!document.body || !document.body.currentStyle) return;
		minmax_elements= new Array();
		window.attachEvent('onresize', minmax_delayout);
	}

	if (cs['max-width'])
		st['maxWidth']= cs['max-width'];

	ms= cs['maxWidth'];
	if (ms && ms!='auto' && ms!='none' && ms!='0' && ms!='') {
		st.minmaxWidth= cs.width;
		minmax_elements[minmax_elements.length]= el;
		minmax_delayout();
	}

	if (cs['min-width'])
		st['minWidth']= cs['min-width'];

	ms= cs['minWidth'];
	if (ms && ms!='auto' && ms!='none' && ms!='0' && ms!='') {
		st.minmaxWidth= cs.width;
		minmax_elements[minmax_elements.length]= el;
		minmax_delayout();
	}
}

var minmax_delaying= false;
function minmax_delayout() {
	if (minmax_delaying) return;
	minmax_delaying= true;
	window.setTimeout(minmax_layout, 0);
}

function minmax_stopdelaying() {
	minmax_delaying= false;
}

function minmax_layout() {
	window.setTimeout(minmax_stopdelaying, 100);
	var i, el, st, cs, optimal, inrange;
	for (i= minmax_elements.length; i-->0;) {
		el= minmax_elements[i]; st= el.style; cs= el.currentStyle;

		st.width= st.minmaxWidth; optimal= el.offsetWidth;
		inrange= true;
		if (inrange && cs.minWidth && cs.minWidth!='0' && cs.minWidth!='auto' && cs.minWidth!='') {
			st.width= cs.minWidth;
			inrange= (el.offsetWidth<optimal);
		}
		if (inrange && cs.maxWidth && cs.maxWidth!='none' && cs.maxWidth!='auto' && cs.maxWidth!='') {
			st.width= cs.maxWidth;
			inrange= (el.offsetWidth>optimal);
		}
		if (inrange) st.width= st.minmaxWidth;
	}
}

var minmax_SCANDELAY= 500;

function minmax_scan() {
	var el;
	for (var i= 0; i<document.all.length; i++) {
		el= document.all[i];
		if (!el.minmax_bound) {
			el.minmax_bound= true;
			minmax_bind(el);
		}
	}
}

var minmax_scanner;
function minmax_stop() {
	window.clearInterval(minmax_scanner);
	minmax_scan();
}

minmax_scan();
minmax_scanner= window.setInterval(minmax_scan, minmax_SCANDELAY);
window.attachEvent('onload', minmax_stop);

@end @*/