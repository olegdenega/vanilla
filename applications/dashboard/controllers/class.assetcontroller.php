<?php
/**
 * Manages asset endpoints.
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package Dashboard
 * @since 2.0
 */

/**
 * Handles /asset endpoint.
 */
class AssetController extends DashboardController {

    /**
     * Serve combined CSS assets
     *
     * @param string $themeType Either `desktop` or `mobile`.
     * @param string $filename The basename of the file to serve
     * @since 2.1
     */
    public function css($themeType, $filename) {
        $assetModel = new AssetModel();
        $assetModel->serveCss($themeType, $filename);
    }

    /**
     * Delete an image from config.
     *
     * @param string $config The config value to delete.
     * @throws Gdn_UserException
     */
    public function deleteConfigImage($config = '') {
        if (!Gdn::request()->isAuthenticatedPostBack()) {
            throw new Gdn_UserException('The CSRF token is invalid.', 403);
        }
        $this->permission('Garden.Settings.Manage');

        if (!$config) {
            return;
        }

        $config = urldecode($config);

        if (c($config, false) !== false) {
            $upload = new Gdn_UploadImage();
            if ($upload->delete(c($config))) {
                // Fore extra safety, ensure an image has been deleted before removing from config.
                removeFromConfig($config);
            }
            $this->informMessage(t('Image deleted.'));
        }

        $this->render('blank', 'utility', 'dashboard');
    }
}
