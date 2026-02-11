<?php

namespace WLMI\App\Helper;

use Wlr\App\Models\EarnCampaignTransactions;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class Loyalty {

	/**
	 * Determines if the plugin is in the Pro version based on filters.
	 *
	 * @return bool True if the plugin is in the Pro version, false otherwise.
	 */
	public static function isPro() {
		return apply_filters( 'wlr_is_pro', false );
	}
}