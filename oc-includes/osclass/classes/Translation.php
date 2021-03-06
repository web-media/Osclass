<?php use Gettext\Translator;

if (!defined('ABS_PATH')) {
    exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

/*
 *  Copyright 2020 Osclass
 *  Maintained and supported by Mindstellar Community
 *  https://github.com/mindstellar/Osclass
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Class Translation
 */
class Translation
{
    private static $instance;
    private $translator;

    /**
     * Translation constructor.
     *
     * @param bool $install
     */
    public function __construct($install = false)
    {
        $this->translator = new Translator();
        if (!$install) {
            // get user/admin locale
            if (OC_ADMIN) {
                $locale = osc_current_admin_locale();
            } else {
                $locale = osc_current_user_locale();
            }

            // load core
            $core_file = osc_apply_filter('mo_core_path', osc_translations_path() . $locale . '/core.mo', $locale);
            $this->_load($core_file, 'core');

            // load messages
            $domain        = osc_apply_filter('theme', osc_theme());
            $messages_file = osc_apply_filter(
                'mo_theme_messages_path',
                osc_themes_path() . $domain . '/languages/' . $locale . '/messages.mo',
                $locale,
                $domain
            );

            if (!file_exists($messages_file)) {
                $messages_file =
                    osc_apply_filter(
                        'mo_core_messages_path',
                        osc_translations_path() . $locale . '/messages.mo',
                        $locale
                    );
            }
            $this->_load($messages_file, 'messages');

            // load theme
            $theme_file =
                osc_apply_filter(
                    'mo_theme_path',
                    osc_themes_path() . $domain . '/languages/' . $locale . '/theme.mo',
                    $locale,
                    $domain
                );
            if (!file_exists($theme_file)) {
                if (!file_exists(osc_themes_path() . $domain)) {
                    $domain = osc_theme();
                }
                $theme_file = osc_translations_path() . $locale . '/theme.mo';
            }
            $this->_load($theme_file, $domain);

            // load plugins
            $aPlugins = Plugins::listEnabled();
            foreach ($aPlugins as $plugin) {
                $domain      = preg_replace('|/.*|', '', $plugin);
                $plugin_file = osc_apply_filter(
                    'mo_plugin_path',
                    osc_plugins_path() . $domain . '/languages/' . $locale . '/messages.mo',
                    $locale,
                    $domain
                );
                if (file_exists($plugin_file)) {
                    $this->_load($plugin_file, $domain);
                }
            }
        } else {
            $core_file = osc_translations_path() . osc_current_admin_locale() . '/core.mo';
            $this->_load($core_file, 'core');
        }
    }

    /**
     * @param $file
     * @param $domain
     *
     * @return bool|\Translation
     */
    public function _load($file, $domain)
    {
        if (!file_exists($file)) {
            return false;
        }
        //Create a Translations instance using a po file
        $translations = Gettext\Translations::fromMoFile($file);

        $translations->addFromMoFile($file);
        $translations->setDomain($domain);

        $this->translator->loadTranslations($translations);

        return $this;
    }

    /**
     * @param bool $install
     *
     * @return \Translation
     */
    public static function newInstance($install = false)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($install);
        }

        return self::$instance;
    }

    /**
     * @return \Translation
     */
    public static function init()
    {
        self::$instance = new self();

        return self::$instance;
    }

    /**
     * @return \Gettext\Translator
     */
    public function _get()
    {
        return $this->translator;
    }
}
