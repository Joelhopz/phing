<?php

/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */

namespace Phing\Test\Task\Ext\SymfonyConsole;

use Phing\Task\Ext\SymfonyConsole\Arg;
use Phing\Task\Ext\SymfonyConsole\SymfonyConsole;
use PHPUnit_Framework_TestCase;

/**
 * Test class for the SymfonyConsoleTask.
 *
 * @author  Nuno Costa <nuno@francodacosta.com>
 * @version $Id$
 * @package phing.tasks.ext
 */
class SymfonyConsoleTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SymfonyConsole
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new SymfonyConsole();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::setCommand
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getCommand
     */
    public function testSetGetCommand()
    {
        $o = $this->object;
        $o->setCommand('foo');
        $this->assertEquals('foo', $o->getCommand());
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::setConsole
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getConsole
     */
    public function testSetGetConsole()
    {
        $o = $this->object;
        $o->setConsole('foo');
        $this->assertEquals('foo', $o->getConsole());
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::setDebug
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getDebug
     */
    public function testSetGetDebug()
    {
        $o = $this->object;
        $o->setDebug(false);
        $this->assertEquals(false, $o->getDebug());
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::createArg
     */
    public function testCreateArg()
    {
        $o = $this->object;
        $arg = $o->createArg();
        $this->assertTrue(get_class($arg) == Arg::class);
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getArgs
     */
    public function testGetArgs()
    {
        $o = $this->object;
        $arg = $o->createArg();
        $arg = $o->createArg();
        $arg = $o->createArg();
        $this->assertTrue(count($o->getArgs()) == 3);
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getCmdString
     * @todo Implement testMain().
     */
    public function testGetCmdString()
    {
        $o = $this->object;
        $arg = $o->createArg();
        $arg->setName('name');
        $arg->setValue('value');

        $o->setCommand('command');
        $o->setConsole('console');

        $ret = "console command --name=value";

        $this->assertEquals($ret, $o->getCmdString());
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getCmdString
     */
    public function testNoDebugGetCmdString()
    {
        $o = $this->object;
        $arg = $o->createArg();
        $arg->setName('name');
        $arg->setValue('value');

        $o->setCommand('command');
        $o->setConsole('console');
        $o->setDebug(false);

        $ret = "console command --name=value --no-debug";

        $this->assertEquals($ret, $o->getCmdString());
    }

    /**
     * @covers \Phing\Task\Ext\SymfonyConsole\SymfonyConsole::getCmdString
     */
    public function testNoDebugOnlyOnce()
    {
        $o = $this->object;
        $arg = $o->createArg();
        $arg->setName('no-debug');

        $o->setCommand('command');
        $o->setConsole('console');
        $o->setDebug(false);

        $ret = "console command --no-debug";

        $this->assertEquals($ret, $o->getCmdString());
    }
}