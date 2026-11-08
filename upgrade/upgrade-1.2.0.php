<?php
/** @author 30knees */
if (!defined('_PS_VERSION_')) exit;
function upgrade_module_1_2_0($module)
{
    // Keep original settings, credentials and hook positions.
    return $module->checkRuntime();
}
