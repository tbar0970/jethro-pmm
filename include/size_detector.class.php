<?php
class SizeDetector {
	static function printFormFields() {
		?>
		<input type="hidden" id="size-detector-width" name="_sizeDetector[width]" value="" />
		<input type="hidden" id="size-detector-height" name="_sizeDetector[height]" value="" />
		<script>
		$(document).ready(function() {
			var w=window,d=document,e=d.documentElement,g=d.getElementsByTagName('body')[0],x=w.innerWidth||e.clientWidth||g.clientWidth,y=w.innerHeight||e.clientHeight||g.clientHeight;
			$('#size-detector-width').val(x);
			$('#size-detector-height').val(y);
			if (-1 != document.location.href.indexOf('showsize=1')) {
				document.write('<p>'+x+' x '+y+'</p>');
				document.write('<p>Jquery:'+$(document).width()+' x '+$(document).height()+'</p>');
			}
		});
		</script>
		<?php
		if (!empty($_REQUEST['showsize'])) bam($_SERVER['HTTP_USER_AGENT']);
	}

	static function processRequest() {
		if (isset($_REQUEST['_sizeDetector'])) {
			$_SESSION['_sizeDetector'] = $_REQUEST['_sizeDetector'];
		}
	}
	
	static function getHeight() {
		if (isset($_SESSION['_sizeDetector']) && isset($_SESSION['_sizeDetector']['height'])) {
			return $_SESSION['_sizeDetector']['height'];
		} else {
			return NULL;
		}
	}

	static function getWidth() {
		if (isset($_SESSION['_sizeDetector']) && isset($_SESSION['_sizeDetector']['width'])) {
			return $_SESSION['_sizeDetector']['width'];
		} else {
			return NULL;
		}
	}

	static function isWide()  {
		if ($w = self::getWidth()) {
			return $w > 1024;
		} else {
			return NULL;
		}
	}

	static function isMedium() {
		if (!self::getWidth()) return NULL;
		return !self::isWide() && !self::isNarrow();
	}

	static function isNarrow() {
		if ($w = self::getWidth()) {
			return $w < 640;
		} else {
			return NULL;
		}
	}
}
