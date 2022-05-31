<?php

if (!defined("ABSPATH")) exit;
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('cloudflare-purge');
