<?php
/**
 * Data-visibility bits for contact fields.
 *
 * The values are the shared vocabulary for the members-area directory share
 * consent (#792): _person_share_consent.data_permissions stores a mask of these
 * bits, and DATA_PERM_VIEWNAME (1) is the directory-listing bit.  The same
 * values back the (upstream) staff "Viewable Person Fields" data permissions.
 *
 * The two features ask different questions:
 *   - staff field visibility: may this viewer see this field type?
 *   - members-area consent: has the viewed person consented to share this field
 *     with this viewer?
 * Keep the masks separate; data_perm_bit() and data_perm_mask_has() only provide
 * the shared symbol vocabulary. The SQL functions dataPermBit() and
 * hasDataPerm() mirror them for views.
 *
 * @see docs/docs/developer/reference/member-share-consent.md
 */
define('DATA_PERM_VIEWADDRESS', 134217728);
define('DATA_PERM_VIEWEMAIL', 536870912);
define('DATA_PERM_VIEWMOBILE', 268435456);
define('DATA_PERM_VIEWREMARKS', 33554432);
define('DATA_PERM_VIEWCUSTOMFIELDS', 67108864);
define('DATA_PERM_VIEWSENSITIVE', 16777216);

/**
 * Directory-listing bit for the members-area share consent. It lives in this
 * file so the consent mask and the staff visibility mask share one bit
 * vocabulary, but it is deliberately absent from get_data_permission_levels():
 * staff accounts always see names, so it is never offered on the user-account
 * form. @see docs/docs/developer/reference/member-share-consent.md
 */
define('DATA_PERM_VIEWNAME', 1);

/**
 * Data permissions in display order.
 * @return array<int, array{0: string, 1: string, 2: string}> bit => (symbol, label, feature code)
 */
function get_data_permission_levels(): array
{
	return Array(
		DATA_PERM_VIEWADDRESS      => Array('VIEWADDRESS', 'Address', ''),
		DATA_PERM_VIEWEMAIL        => Array('VIEWEMAIL', 'Email', ''),
		DATA_PERM_VIEWMOBILE       => Array('VIEWMOBILE', 'Mobile', ''),
		DATA_PERM_VIEWREMARKS      => Array('VIEWREMARKS', 'Contact remarks', ''),
		DATA_PERM_VIEWCUSTOMFIELDS => Array('VIEWCUSTOMFIELDS', 'Custom fields', ''),
		DATA_PERM_VIEWSENSITIVE    => Array('VIEWSENSITIVE', 'Sensitive custom fields', ''),
	);
}

/**
 * Return the bit for a data-permission symbol.
 *
 * @param string $symbol
 * @return int 0 when the symbol is unknown
 */
function data_perm_bit(string $symbol): int
{
	static $bits = null;
	if ($bits === null) {
		$bits = Array('VIEWNAME' => DATA_PERM_VIEWNAME);
		foreach (get_data_permission_levels() as $bit => $level) {
			$bits[$level[0]] = $bit;
		}
	}
	return $bits[$symbol] ?? 0;
}

/**
 * Test whether a data-permission mask contains a symbol.
 *
 * @param int $mask
 * @param string $symbol
 * @return bool
 */
function data_perm_mask_has(int $mask, string $symbol): bool
{
	$bit = data_perm_bit($symbol);
	return $bit !== 0 && ($mask & $bit) !== 0;
}
