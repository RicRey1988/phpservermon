<?php

/**
 * PHP Server Monitor
 * Monitor your servers and websites.
 *
 * This file is part of PHP Server Monitor.
 * PHP Server Monitor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHP Server Monitor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP Server Monitor.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     phpservermon
 * @author      Pepijn Over <pep@mailbox.org>
 * @copyright   Copyright (c) 2008-2017 Pepijn Over <pep@mailbox.org>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 **/

namespace psm\Module\Config\Controller;

use psm\Module\AbstractController;
use psm\Notification\DeliveryResult;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use psm\Service\Database;

class ConfigController extends AbstractController
{

    /**
     * Checkboxes
     * @var array $checkboxes
     */
    protected $checkboxes = array(
        'proxy',
        'email_status',
        'email_add_url',
        'email_smtp',
        'sms_status',
        'discord_status',
        'pushover_status',
        'webhook_status',
        'telegram_status',
        'telegram_add_url',
        'log_status',
        'log_email',
        'log_sms',
        'log_discord',
        'log_pushover',
        'log_webhook',
        'log_telegram',
        'show_update',
        'combine_notifications',
        'dirauth_status',
        'authdir_ldapfollowref',
    );

    /**
     * Fields for saving
     * @var array $fields
     */
    protected $fields = array(
        'proxy_url',
        'proxy_user',
        'proxy_password',
        'email_from_name',
        'email_from_email',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_username',
        'sms_gateway_username',
        'sms_gateway_password',
        'sms_from',
        'webhook_url',
        'webhook_json',
        'pushover_api_token',
        'telegram_api_token',
        'user_agent',
        'site_title',
        'authdir_host_locn',
        'authdir_host_port',
        'authdir_userdomain',
        'authdir_ldapver',
        'authdir_basedn',
        'authdir_usernameattrib',
        'authdir_groupnameattrib',
        'authdir_groupmemattrib',
        'authdir_usercontainerrdn',
        'authdir_groupcontainerrdn',
        'authdir_groupname',
    );

    /**
     * Fields for saving encrypted.
     * @var array
     */
    protected $encryptedFields = [
        'email_smtp_password'
    ];

    private $default_tab = 'general';

    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);

        $this->setMinUserLevelRequired(PSM_USER_ADMIN);
        $this->setCSRFKey('config');

        $this->setActions(array(
            'index', 'save',
        ), 'index');
    }

    /**
     * Populate all the config fields with values from the database
     *
     * @return string
     */
    protected function executeIndex()
    {
        $this->twig->addGlobal('subtitle', psm_get_lang('menu', 'config'));
        $tpl_data = $this->getLabels();

        $config_db = $this->db->select(
            PSM_DB_PREFIX . 'config',
            null,
            array('key', 'value')
        );

        $config = array();
        foreach ($config_db as $entry) {
            $config[$entry['key']] = $entry['value'];
        }

        // generate language array
        $lang_keys = psm_get_langs();
        $tpl_data['language_current'] = (isset($config['language']))
                ? $config['language']
                : 'en_US';
        $tpl_data['languages'] = array();
        foreach ($lang_keys as $key => $label) {
            $tpl_data['languages'][] = array(
                'value' => $key,
                'label' => $label,
            );
        }

        // generate sms_gateway array
        $sms_gateways = psm_get_sms_gateways();
        $tpl_data['sms_gateway'] = array();
        foreach ($sms_gateways as $sms_gateway => $label) {
            $tpl_data['sms_gateway'][] = array(
                'value' => $sms_gateway,
                'label' => $label,
            );
        }

        foreach (array("status", "offline", "always") as $alert_type) {
            $tpl_data['alert_type'][] = array(
                'value' => $alert_type,
                'label' => psm_get_lang('config', 'alert_type_' . $alert_type),
            );
        }

        foreach (array("20", "10") as $authdir_defaultrole) {
            $tpl_data['authdir_defaultroles'][] = array(
                'value' => $authdir_defaultrole,
                'label' => psm_get_lang('users', 'level_' . $authdir_defaultrole),
            );
        }

        foreach (array("openldap", "adds", "adlds") as $authdir_type) {
            $tpl_data['authdir_type'][] = array(
                'value' => $authdir_type,
                'label' => psm_get_lang('config', 'authdir_type_' . $authdir_type),
            );
        }

        $tpl_data['email_smtp_security'] = array(
            array(
                'value' => '',
                'label' => psm_get_lang('config', 'email_smtp_security_none')
            ),
            array(
                'value' => 'ssl',
                'label' => 'SSL'
            ),
            array(
                'value' => 'tls',
                'label' => 'TLS'
            )
        );

        $tpl_data['sms_gateway_selected'] = isset($config['sms_gateway']) ?
            $config['sms_gateway'] : current($sms_gateways);
        $tpl_data['alert_type_selected'] = isset($config['alert_type']) ?
            $config['alert_type'] : '';
        $tpl_data['authdir_type_selected'] =  isset($config['authdir_type']) ?
            $config['authdir_type'] : '';
        $tpl_data['authdir_defaultrole_selected'] =  isset($config['authdir_defaultrole']) ?
            $config['authdir_defaultrole'] : '20';
        $tpl_data['email_smtp_security_selected'] =  isset($config['email_smtp_security']) ?
            $config['email_smtp_security'] : '';
        $tpl_data['auto_refresh_servers'] = isset($config['auto_refresh_servers']) ?
            $config['auto_refresh_servers'] : '0';
        $tpl_data['log_retention_period'] = isset($config['log_retention_period']) ?
            $config['log_retention_period'] : '365';
        $tpl_data['password_encrypt_key'] = isset($config['password_encrypt_key']) ?
            $config['password_encrypt_key'] : sha1(microtime());

        foreach ($this->checkboxes as $input_key) {
            $tpl_data[$input_key . '_checked'] =
                (isset($config[$input_key]) && (int) $config[$input_key] == 1)
                ? 'checked="checked"'
                : '';
        }
        foreach ($this->fields as $input_key) {
            $tpl_data[$input_key] = (isset($config[$input_key])) ? $config[$input_key] : '';
        }

        $tpl_data['user_agent'] = empty($tpl_data['user_agent']) ?
            'Mozilla/5.0 (compatible; phpservermon/' .
            PSM_VERSION . '; +https://github.com/phpservermon/phpservermon)' : $tpl_data['user_agent'];

        $tpl_data['site_title'] = empty($tpl_data['site_title']) ?
            strtoupper(psm_get_lang('system', 'title')) : $tpl_data['site_title'];

        // encrypted fields
        foreach ($this->encryptedFields as $encryptedField) {
            $tpl_data[$encryptedField] = '';
        }

        $tpl_data[$this->default_tab . '_active'] = 'active';

        $testmodals = array('email', 'sms', 'discord', 'webhook', 'pushover', 'telegram');

        foreach ($testmodals as $modal_id) {
            $modal = new \psm\Util\Module\Modal(
                $this->twig,
                'test' . ucfirst($modal_id),
                \psm\Util\Module\Modal::MODAL_TYPE_OKCANCEL
            );
            $this->addModal($modal);
            $modal->setTitle(psm_get_lang('servers', 'send_' . $modal_id));
            $modal->setMessage(psm_get_lang('config', 'test_' . $modal_id));
            $modal->setOKButtonLabel(psm_get_lang('config', 'send'));
        }

        return $this->twig->render('module/config/config.tpl.html', $tpl_data);
    }

    /**
     * If a post has been done, gather all the posted data
     * and save it to the database
     */
    protected function executeSave()
    {
        if (!empty($_POST)) {
            // save new config
            $clean = array(
                'language' => $_POST['language'],
                'site_title' => $_POST['site_title'],
                'sms_gateway' => $_POST['sms_gateway'],
                'alert_type' => $_POST['alert_type'],
                'authdir_defaultrole' => $_POST['authdir_defaultrole'],
                'authdir_type' => $_POST['authdir_type'],
                'email_smtp_security' =>
                    in_array($_POST['email_smtp_security'], array('', 'ssl', 'tls'))
                    ? $_POST['email_smtp_security']
                    : '',
                'auto_refresh_servers' => intval(psm_POST('auto_refresh_servers', 0)),
                'log_retention_period' => intval(psm_POST('log_retention_period', 365)),
                'password_encrypt_key' => psm_POST('password_encrypt_key', sha1(microtime()))
            );
            foreach ($this->checkboxes as $input_key) {
                $clean[$input_key] = (isset($_POST[$input_key])) ? '1' : '0';
            }
            foreach ($this->fields as $input_key) {
                if (isset($_POST[$input_key])) {
                    $clean[$input_key] = $_POST[$input_key];
                }
            }
            foreach ($this->encryptedFields as $encryptedField) {
                $value = filter_input(INPUT_POST, $encryptedField);
                if ($value !== null && $value !== '') {
                    $clean[$encryptedField] =  psm_password_encrypt(psm_get_conf('password_encrypt_key'), $value);
                }
                // else { leave as is }
            }
            $language_refresh = ($clean['language'] != psm_get_conf('language'));
            foreach ($clean as $key => $value) {
                psm_update_conf($key, $value);
            }
            $this->addMessage(psm_get_lang('config', 'updated'), 'success');

            if (!empty($_POST['test_email'])) {
                $this->testEmail();
            } elseif (!empty($_POST['test_sms'])) {
                $this->testSMS();
            } elseif (!empty($_POST['test_discord'])) {
                $this->testDiscord();
            } elseif (!empty($_POST['test_pushover'])) {
                $this->testPushover();
            }elseif (!empty($_POST['test_webhook'])) {
                $this->testWebhook();
            } elseif (!empty($_POST['test_telegram'])) {
                $this->testTelegram();
            }

            if ($language_refresh) {
                header('Location: ' . psm_build_url(array('mod' => 'config'), true, false));
                die();
            }

            if (isset($_POST['general_submit'])) {
                $this->default_tab = 'general';
            } elseif (isset($_POST['auth_submit'])) {
                $this->default_tab = 'auth';
            } elseif (isset($_POST['email_submit']) || !empty($_POST['test_email'])) {
                $this->default_tab = 'email';
            } elseif (isset($_POST['sms_submit']) || !empty($_POST['test_sms'])) {
                $this->default_tab = 'sms';
            } elseif (isset($_POST['discord_submit']) || !empty($_POST['test_discord'])) {
                $this->default_tab = 'discord';
            } elseif (isset($_POST['pushover_submit']) || !empty($_POST['test_pushover'])) {
                $this->default_tab = 'pushover';
            } elseif (isset($_POST['webhook_submit']) || !empty($_POST['test_webhook'])) {
                $this->default_tab = 'webhook';
            } elseif (isset($_POST['telegram_submit']) || !empty($_POST['test_telegram'])) {
                $this->default_tab = 'telegram';
            }
        }
        return $this->runAction('index');
    }

    /**
     * Execute email test
     *
     * @todo move test to separate class
     */
    protected function testEmail()
    {
        $this->sendTestNotification('email');
    }

    /**
     * Execute SMS test
     *
     * @todo move test to separate class
     */
    protected function testSMS()
    {
        $this->sendTestNotification('sms');
    }

    /**
     * Execute Discord test
     *
     * @todo move test to separate class
     */
    protected function testDiscord()
    {
        $this->sendTestNotification('discord');
    }

    /** Execute webhook test
     *
     * @todo move test to separate class
     */
    protected function testWebhook()
    {
        $this->sendTestNotification('webhook');
    }

    /**
     * Execute pushover test
     *
     * @todo move test to separate class
     */
    protected function testPushover()
    {
        $this->sendTestNotification('pushover');
    }

    /**
     * Execute telegram test
     *
     * @todo move test to separate class
     */
    protected function testTelegram()
    {
        $this->sendTestNotification('telegram');
    }

    protected function sendTestNotification(string $channelName): void
    {
        $user = $this->getUser()->getUser();
        $attributes = get_object_vars($user);
        $recipient = new Recipient((int) ($attributes['user_id'] ?? 0), $attributes);
        $prefix = '[PRUEBA 4.0.0-hs] ';
        $message = new NotificationMessage(
            $prefix . psm_get_lang('config', 'test_subject'),
            $prefix . psm_get_lang('config', 'test_message')
        );

        try {
            $result = $this->container->get('notification.registry')->get($channelName)->send($message, $recipient);
        } catch (\Throwable) {
            $result = DeliveryResult::temporaryFailure('Notification test failed unexpectedly.');
        }

        $this->addMessage($result->message(), $result->isSuccess() ? 'success' : 'error');
    }

    protected function getLabels()
    {
        return array(
            'label_tab_email' => psm_get_lang('config', 'tab_email'),
            'label_tab_sms' => psm_get_lang('config', 'tab_sms'),
            'label_tab_discord' => psm_get_lang('config', 'tab_discord'),
            'label_tab_pushover' => psm_get_lang('config', 'tab_pushover'),
            'label_tab_webhook' => psm_get_lang('config', 'tab_webhook'),
            'label_tab_telegram' => psm_get_lang('config', 'tab_telegram'),
            'label_tab_auth' => psm_get_lang('config', 'tab_auth'),
            'label_settings_email' => psm_get_lang('config', 'settings_email'),
            'label_settings_sms' => psm_get_lang('config', 'settings_sms'),
            'label_settings_discord' => psm_get_lang('config', 'settings_discord'),
            'label_settings_webhook' => psm_get_lang('config', 'settings_webhook'),
            'label_settings_pushover' => psm_get_lang('config', 'settings_pushover'),
            'label_settings_telegram' => psm_get_lang('config', 'settings_telegram'),
            'label_settings_dirauth' => psm_get_lang('config', 'settings_dirauth'),
            'label_settings_notification' => psm_get_lang('config', 'settings_notification'),
            'label_settings_log' => psm_get_lang('config', 'settings_log'),
            'label_settings_proxy' => psm_get_lang('config', 'settings_proxy'),
            'label_general' => psm_get_lang('config', 'general'),
            'label_language' => psm_get_lang('config', 'language'),
            'label_show_update' => psm_get_lang('config', 'show_update'),
            'label_password_encrypt_key' => psm_get_lang('config', 'password_encrypt_key'),
            'label_password_encrypt_key_note' => psm_get_lang('config', 'password_encrypt_key_note'),
            'label_proxy' => psm_get_lang('config', 'proxy'),
            'label_proxy_url' => psm_get_lang('config', 'proxy_url'),
            'label_proxy_user' => psm_get_lang('config', 'proxy_user'),
            'label_proxy_password' => psm_get_lang('config', 'proxy_password'),
            'label_email_add_url' => psm_get_lang('config', 'email_add_url'),
            'label_email_status' => psm_get_lang('config', 'email_status'),
            'label_email_from_email' => psm_get_lang('config', 'email_from_email'),
            'label_email_from_name' => psm_get_lang('config', 'email_from_name'),
            'label_email_smtp' => psm_get_lang('config', 'email_smtp'),
            'label_email_smtp_host' => psm_get_lang('config', 'email_smtp_host'),
            'label_email_smtp_port' => psm_get_lang('config', 'email_smtp_port'),
            'label_email_smtp_security' => psm_get_lang('config', 'email_smtp_security'),
            'label_email_smtp_username' => psm_get_lang('config', 'email_smtp_username'),
            'label_email_smtp_password' => psm_get_lang('config', 'email_smtp_password'),
            'label_email_smtp_noauth' => psm_get_lang('config', 'email_smtp_noauth'),
            'label_sms_status' => psm_get_lang('config', 'sms_status'),
            'label_sms_gateway' => psm_get_lang('config', 'sms_gateway'),
            'label_sms_gateway_username' => psm_get_lang('config', 'sms_gateway_username'),
            'label_sms_gateway_password' => psm_get_lang('config', 'sms_gateway_password'),
            'label_sms_from' => psm_get_lang('config', 'sms_from'),
            'label_discord_status' => psm_get_lang('config', 'discord_status'),
            'label_discord_description' => psm_get_lang('config', 'discord_description'),
            'label_webhook_description' => psm_get_lang('config', 'webhook_description'),
            'label_webhook_status' => psm_get_lang('config', 'webhook_status'),
            'label_webhook_url' => psm_get_lang('config', 'webhook_url'),
            'label_webhook_url_description' => psm_get_lang('config', 'webhook_url_description'),
            'label_webhook_json' => psm_get_lang('config', 'webhook_json'),
            'label_webhook_json_description' => psm_get_lang('config', 'webhook_json_description'),
            'label_pushover_description' => psm_get_lang('config', 'pushover_description'),
            'label_pushover_status' => psm_get_lang('config', 'pushover_status'),
            'label_pushover_clone_app' => psm_get_lang('config', 'pushover_clone_app'),
            'pushover_clone_url' => PSM_PUSHOVER_CLONE_URL,
            'label_pushover_api_token' => psm_get_lang('config', 'pushover_api_token'),
            'label_pushover_api_token_description' => sprintf(
                psm_get_lang('config', 'pushover_api_token_description'),
                PSM_PUSHOVER_CLONE_URL
            ),
            'label_telegram_description' => psm_get_lang('config', 'telegram_description'),
            'label_telegram_status' => psm_get_lang('config', 'telegram_status'),
            'label_telegram_add_url' => psm_get_lang('config', 'telegram_add_url'),
            'label_telegram_api_token' => psm_get_lang('config', 'telegram_api_token'),
            'label_telegram_api_token_description' => psm_get_lang('config', 'telegram_api_token_description'),
            'label_dirauth_status' => psm_get_lang('config', 'dirauth_status'),
            'label_authdir_host_locn' => psm_get_lang('config', 'authdir_host_locn'),
            'label_authdir_host_port' => psm_get_lang('config', 'authdir_host_port'),
            'label_authdir_type' => psm_get_lang('config', 'authdir_type'),
            'label_authdir_type_description' => psm_get_lang('config', 'authdir_type_description'),
            'label_authdir_userdomain' => psm_get_lang('config', 'authdir_userdomain'),
            'label_authdir_userdomain_description' => psm_get_lang('config', 'authdir_userdomain_description'),
            'label_authdir_ldapver' => psm_get_lang('config', 'authdir_ldapver'),
            'label_authdir_ldapver_description' => psm_get_lang('config', 'authdir_ldapver_description'),
            'label_authdir_ldapfollowref' => psm_get_lang('config', 'authdir_ldapfollowref'),
            'label_authdir_ldapfollowref_description' => psm_get_lang('config', 'authdir_ldapfollowref_description'),
            'label_authdir_basedn' => psm_get_lang('config', 'authdir_basedn'),
            'label_authdir_basedn_description' => psm_get_lang('config', 'authdir_basedn_description'),
            'label_authdir_usernameattrib' => psm_get_lang('config', 'authdir_usernameattrib'),
            'label_authdir_usernameattrib_description' => psm_get_lang('config', 'authdir_usernameattrib_description'),
            'label_authdir_groupnameattrib' => psm_get_lang('config', 'authdir_groupnameattrib'),
            'label_authdir_groupnameattrib_description' =>
                psm_get_lang('config', 'authdir_groupnameattrib_description'),
            'label_authdir_groupmemattrib' => psm_get_lang('config', 'authdir_groupmemattrib'),
            'label_authdir_groupmemattrib_description' => psm_get_lang('config', 'authdir_groupmemattrib_description'),
            'label_authdir_usercontainerrdn' => psm_get_lang('config', 'authdir_usercontainerrdn'),
            'label_authdir_usercontainerrdn_description' =>
                psm_get_lang('config', 'authdir_usercontainerrdn_description'),
            'label_authdir_groupcontainerrdn' => psm_get_lang('config', 'authdir_groupcontainerrdn'),
            'label_authdir_groupcontainerrdn_description' =>
                psm_get_lang('config', 'authdir_groupcontainerrdn_description'),
            'label_authdir_groupname' => psm_get_lang('config', 'authdir_groupname'),
            'label_authdir_groupname_description' => psm_get_lang('config', 'authdir_groupname_description'),
            'label_authdir_defaultrole' => psm_get_lang('config', 'authdir_defaultrole'),
            'label_authdir_defaultrole_description' => psm_get_lang('config', 'authdir_defaultrole_description'),
            'label_alert_type' => psm_get_lang('config', 'alert_type'),
            'label_alert_type_description' => psm_get_lang('config', 'alert_type_description'),
            'label_combine_notifications' => psm_get_lang('config', 'combine_notifications'),
            'label_combine_notifications_description' => psm_get_lang('config', 'combine_notifications_description'),
            'label_log_status' => psm_get_lang('config', 'log_status'),
            'label_log_status_description' => psm_get_lang('config', 'log_status_description'),
            'label_log_email' => psm_get_lang('config', 'log_email'),
            'label_log_sms' => psm_get_lang('config', 'log_sms'),
            'label_log_discord' => psm_get_lang('config', 'log_discord'),
            'label_log_pushover' => psm_get_lang('config', 'log_pushover'),
            'label_log_webhook' => psm_get_lang('config', 'log_webhook'),
            'label_log_telegram' => psm_get_lang('config', 'log_telegram'),
            'label_alert_proxy' => psm_get_lang('config', 'alert_proxy'),
            'label_alert_proxy_url' => psm_get_lang('config', 'alert_proxy_url'),
            'label_auto_refresh' => psm_get_lang('config', 'auto_refresh'),
            'label_auto_refresh_description' => psm_get_lang('config', 'auto_refresh_description'),
            'label_seconds' => psm_get_lang('system', 'seconds'),
            'label_save' => psm_get_lang('system', 'save'),
            'label_test' => psm_get_lang('config', 'test'),
            'label_log_retention_period' => psm_get_lang('config', 'log_retention_period'),
            'label_log_retention_period_description' => psm_get_lang('config', 'log_retention_period_description'),
            'label_log_retention_days' => psm_get_lang('config', 'log_retention_days'),
            'label_days' => psm_get_lang('config', 'log_retention_days'),
            'label_leave_blank' => psm_get_lang('users', 'password_leave_blank'),
            'label_user_agent' => psm_get_lang('config', 'user_agent'),
            'label_user_agent_key_note' => psm_get_lang('config', 'user_agent_key_note'),
            'label_site_title' => psm_get_lang('config', 'site_title'),
        );
    }
}
