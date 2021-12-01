<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class FormTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testForm()
    {
        $app = $this->getApp();
        $tempDir = $this->getTempDir();

        $formFilename = $tempDir . '/form1.php';
        file_put_contents($formFilename, '<?php

echo \'<form>\';
echo \'<input type="text" name="key1"/>\';
echo \'</form>\';

');

        $html = $app->components->process('<component src="form" filename="' . htmlentities($formFilename) . '">');
        $this->assertTrue(strpos($html, '<form data-form-id') !== false);
        $this->assertTrue(strpos($html, '<input type="text" name="key1">') !== false);
    }
}
