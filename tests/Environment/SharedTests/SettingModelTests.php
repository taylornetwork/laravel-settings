<?php

namespace Envorra\LaravelSettings\Tests\Environment\SharedTests;

use Envorra\LaravelSettings\Collections\SettingsCollection;
use Envorra\LaravelSettings\Exceptions\DataTypeException;
use Envorra\LaravelSettings\Models\Setting;

trait SettingModelTests
{
    /**
     * @throws DataTypeException
     */
    protected function assertSettingIsValid(Setting $setting): void
    {
        $this->assertIsDataType($setting->getDataType(), $setting->value);
    }

    /**
     * @throws DataTypeException
     */
    protected function assertSettingsAreValid(SettingsCollection $collection): void
    {
        foreach($collection as $setting) {
            $this->assertSettingIsValid($setting);
        }
    }
}
