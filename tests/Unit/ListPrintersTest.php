<?php

use PHPUnit\Framework\TestCase;


class ListPrintersTest extends TestCase
{
    public function testListPrinters()
    {
        $output = shell_exec('lpstat -a');
        echo $output;
        $this->assertNotEmpty($output);
    }

    public function testPrinterIsAvailable()
    {
        $output = shell_exec('lpstat -a');
        $this->assertStringContainsString('HP-LaserJet-Pro-Stan1', $output, 'Printer HP-LaserJet-Pro-Stan1 is not available');
    }

    public function testSendTestPageToPrinter()
    {
        $testFile = tempnam(sys_get_temp_dir(), 'print_test_');
        file_put_contents($testFile, "Test page from PHPUnit\n");
        $cmd = sprintf('lp -d %s %s 2>&1', escapeshellarg('HP-LaserJet-Pro-Stan1'), escapeshellarg($testFile));
        $output = shell_exec($cmd);
        unlink($testFile);
        $this->assertIsString($output);
        $this->assertStringNotContainsString('error', strtolower($output), 'Error sending test page to printer');
    }
}
