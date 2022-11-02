<?php
function getDefInvi18nSlug($string) {
	return 'DEF_INV_' . strtoupper(str_replace(' ','_',$string) );
}
?>
