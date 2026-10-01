<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

final class MyModuleTest extends TestCaseSymconValidation
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();
    }

    public function testValidateModule(): void
    {
        $this->validateModule(__DIR__ . '/../MyModule');
    }

    public function testStrictLifecycleAndAction(): void
    {
        $instanceID = $this->createInstance();
        IPS_ApplyChanges($instanceID);

        $switchID = IPS_GetObjectIDByIdent('Switch', $instanceID);
        $statusID = IPS_GetObjectIDByIdent('Status', $instanceID);

        $this->assertTrue(IPS_VariableExists($switchID));
        $this->assertTrue(IPS_VariableExists($statusID));
        $this->assertSame(VARIABLETYPE_BOOLEAN, IPS_GetVariable($switchID)['VariableType']);
        $this->assertFalse(GetValueBoolean($switchID));
        $this->assertSame(104, IPS_GetInstance($instanceID)['InstanceStatus']);

        $module = IPS\InstanceManager::getInstanceInterface($instanceID);
        $module->RequestAction('Switch', true);
        $this->assertTrue(GetValueBoolean($switchID));

        IPS_SetProperty($instanceID, 'Hostname', 'example.test');
        IPS_SetProperty($instanceID, 'Interval', 60);
        IPS_ApplyChanges($instanceID);

        $this->assertSame(102, IPS_GetInstance($instanceID)['InstanceStatus']);
        $updateFunction = $this->getModuleConfiguration()['prefix'] . '_Update';
        $updateFunction($instanceID);
        $this->assertStringStartsWith('OK @ ', GetValueString($statusID));
    }

    public function testRequestActionRejectsWrongValueType(): void
    {
        $instanceID = $this->createInstance();
        IPS_ApplyChanges($instanceID);

        $this->expectException(InvalidArgumentException::class);
        IPS\InstanceManager::getInstanceInterface($instanceID)->RequestAction('Switch', 'true');
    }

    private function createInstance(): int
    {
        $moduleConfiguration = $this->getModuleConfiguration();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        return IPS_CreateInstance($moduleConfiguration['id']);
    }

    private function getModuleConfiguration(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../MyModule/module.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
