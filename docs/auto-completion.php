<?php

/**
 * @property    Miaoxing\Install\Service\Install $install Install
 */
class InstallMixin {
}

/**
 * @mixin InstallMixin
 */
class AutoCompletion {
}

/**
 * @return AutoCompletion
 */
function wei()
{
    return new AutoCompletion;
}

/** @var Miaoxing\Install\Service\Install $install */
$install = wei()->install;
