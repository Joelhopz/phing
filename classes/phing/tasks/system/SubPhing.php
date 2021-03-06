<?php
/**
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

class SubPhing extends Task
{
    use FileSetAware;

    /** @var Path */
    private $buildpath;

    private $phing;
    private $subTarget;
    private $phingfile = 'build.xml';
    private $genericphingfile;
    private $verbose = false;
    private $inheritAll = false;
    private $inheritRefs = false;
    private $failOnError = true;
    private $output;

    private $properties = [];
    private $references = [];

    /**
     * Assigns an Ant property to another.
     *
     * @param PropertyTask $to the destination property whose content is modified.
     * @param PropertyTask $from the source property whose content is copied.
     */
    private static function copyProperty(PropertyTask $to, PropertyTask $from)
    {
        $to->setName($from->getName());

        if ($from->getValue() !== null) {
            $to->setValue($from->getValue());
        }
        if ($from->getFile() !== null) {
            $to->setFile($from->getFile());
        }
        if ($from->getPrefix() !== null) {
            $to->setPrefix($from->getPrefix());
        }
        if ($from->getRefid() !== null) {
            $to->setRefid($from->getRefid());
        }
        if ($from->getEnvironment() !== null) {
            $to->setEnvironment($from->getEnvironment());
        }
    }

    /**
     * Runs the various sub-builds.
     */
    public function main()
    {
        $filenames = [];
        if ($this->buildpath !== null) {
            $filenames = $this->buildpath->listPaths();
        } elseif (count($this->filesets) > 0) {
            foreach ($this->filesets as $fileset) {
                foreach ($fileset as $filename) {
                    $filenames[] = $filename;
                }
            }
        } else {
            throw new BuildException("No buildpath specified");
        }
        $count = count($filenames);
        if ($count < 1) {
            $this->log('No sub-builds to iterate on', Project::MSG_WARN);
            return;
        }

        $buildException = null;
        foreach ($filenames as $filename) {
            $file = null;
            $subdirPath = null;
            $thrownException = null;
            try {
                $directory = null;
                $file = new PhingFile($filename);
                if ($file->isDirectory()) {
                    if ($this->verbose) {
                        $subdirPath = $file->getPath();
                        $this->log("Entering directory: " . $subdirPath . "\n", Project::MSG_INFO);
                    }
                    if ($this->genericphingfile !== null) {
                        $directory = $file;
                        $file = $this->genericphingfile;
                    } else {
                        $file = new PhingFile($file, $this->phingfile);
                    }
                }
                $this->execute($file, $directory);
                if ($this->verbose && $subdirPath !== null) {
                    $this->log('Leaving directory: ' . $subdirPath . "\n", Project::MSG_INFO);
                }
            } catch (RuntimeException $ex) {
                if (!$this->getProject()->isKeepGoingMode()) {
                    if ($this->verbose && $subdirPath !== null) {
                        $this->log('Leaving directory: ' . $subdirPath . "\n", Project::MSG_INFO);
                    }
                    throw $ex; // throw further
                }
                $thrownException = $ex;
            } catch (Throwable $ex) {
                if (!$this->getProject()->isKeepGoingMode()) {
                    if ($this->verbose && $subdirPath !== null) {
                        $this->log('Leaving directory: ' . $subdirPath . "\n", Project::MSG_INFO);
                    }
                    throw new BuildException($ex);
                }
                $thrownException = $ex;
            }
            if ($thrownException !== null) {
                if ($thrownException instanceof BuildException) {
                    $this->log("File '" . $file
                        . "' failed with message '"
                        . $thrownException->getMessage() . "'.", Project::MSG_ERR);
                    // only the first build exception is reported
                    if ($buildException === null) {
                        $buildException = $thrownException;
                    }
                } else {
                    $this->log("Target '" . $file
                        . "' failed with message '"
                        . $thrownException->getMessage() . "'.", Project::MSG_ERR);
                    $this->log($thrownException->getTraceAsString(), Project::MSG_ERR);
                    if ($buildException === null) {
                        $buildException = new BuildException($thrownException);
                    }
                }
                if ($this->verbose && $subdirPath !== null) {
                    $this->log('Leaving directory: ' . $subdirPath . "\n", Project::MSG_INFO);
                }
            }
        }
        // check if one of the builds failed in keep going mode
        if ($buildException !== null) {
            throw $buildException;
        }
    }

    /**
     * Runs the given target on the provided build file.
     *
     * @param file the build file to execute
     * @param directory the directory of the current iteration
     * @throws BuildException is the file cannot be found, read, is
     *         a directory, or the target called failed, but only if
     *         <code>failOnError</code> is <code>true</code>. Otherwise,
     *         a warning log message is simply output.
     */
    private function execute(PhingFile $file, ?PhingFile $directory)
    {
        if (!$file->exists() || $file->isDirectory() || !$file->canRead()) {
            $msg = "Invalid file: " . $file;
            if ($this->failOnError) {
                throw new BuildException($msg);
            }
            $this->log($msg, Project::MSG_WARN);
            return;
        }

        $this->phing = $this->createPhingTask($directory);
        $phingfilename = $file->getAbsolutePath();
        $this->phing->setPhingfile($phingfilename);
        if ($this->subTarget !== null) {
            $this->phing->setTarget($this->subTarget);
        }

        try {
            if ($this->verbose) {
                $this->log("Executing: " . $phingfilename, Project::MSG_INFO);
            }
            $this->phing->main();
        } catch (BuildException $e) {
            if ($this->failOnError || $this->isHardError($e)) {
                throw $e;
            }
            $this->log("Failure for target '" . $this->subTarget
                . "' of: " . $phingfilename . "\n"
                . $e->getMessage(), Project::MSG_WARN);
        } catch (Throwable $e) {
            if ($this->failOnError || $this->isHardError($e)) {
                throw new BuildException($e);
            }
            $this->log(
                "Failure for target '" . $this->subTarget
                . "' of: " . $phingfilename . "\n"
                . $e,
                Project::MSG_WARN
            );
        } finally {
            $this->phing = null;
        }
    }

    /**
     * Creates the &lt;phing&gt; task configured to run a specific target.
     *
     * @param ?PhingFile $directory : if not null the directory where the build should run
     *
     * @return PhingTask the phing task, configured with the explicit properties and
     *         references necessary to run the sub-build.
     */
    private function createPhingTask(?PhingFile $directory): PhingTask
    {
        $phingTask = new PhingTask($this);
        $phingTask->setHaltOnFailure($this->failOnError);
        $phingTask->init();
        if ($this->subTarget !== null && $this->subTarget !== '') {
            $phingTask->setTarget($this->subTarget);
        }

        if ($this->output !== null) {
            $phingTask->setOutput($this->output);
        }

        if ($directory !== null) {
            $phingTask->setDir($directory);
        } else {
            $phingTask->setUseNativeBasedir(true);
        }

        $phingTask->setInheritAll($this->inheritAll);

        foreach ($this->properties as $p) {
            self::copyProperty($phingTask->createProperty(), $p);
        }

        $phingTask->setInheritRefs($this->inheritRefs);

        foreach ($this->references as $reference) {
            $phingTask->addReference($reference);
        }

        foreach ($this->filesets as $fileset) {
            $phingTask->addFileSet($fileset);
        }

        return $phingTask;
    }

    /** whether we should even try to continue after this error */
    private function isHardError(Throwable $t)
    {
        if ($t instanceof BuildException) {
            return $this->isHardError($t->getPrevious());
        }
    }

    /**
     * This method builds the file name to use in conjunction with directories.
     *
     * <p>Defaults to "build.xml".
     * If <code>genericantfile</code> is set, this attribute is ignored.</p>
     *
     * @param string $phingfile the short build file name. Defaults to "build.xml".
     */
    public function setPhingfile(string $phingfile)
    {
        $this->phingfile = $phingfile;
    }

    /**
     * This method builds a file path to use in conjunction with directories.
     *
     * <p>Use <code>genericantfile</code>, in order to run the same build file
     * with different basedirs.</p>
     * If this attribute is set, <code>antfile</code> is ignored.
     *
     * @param PhingFile $afile (path of the generic ant file, absolute or relative to
     *               project base directory)
     *
     */
    public function setGenericPhingfile(PhingFile $afile)
    {
        $this->genericphingfile = $afile;
    }

    /**
     * The target to call on the different sub-builds. Set to "" to execute
     * the default target.
     *
     * @param target the target
     */
    // REVISIT: Defaults to the target name that contains this task if not specified.

    /**
     * Sets whether to fail with a build exception on error, or go on.
     *
     * @param bool $failOnError the new value for this boolean flag.
     */
    public function setFailonerror(bool $failOnError)
    {
        $this->failOnError = $failOnError;
    }

    public function setTarget(string $target)
    {
        $this->subTarget = $target;
    }

    /**
     * Enable/ disable verbose log messages showing when each sub-build path is entered/ exited.
     * The default value is "false".
     * @param bool $on true to enable verbose mode, false otherwise (default).
     */
    public function setVerbose(bool $on)
    {
        $this->verbose = $on;
    }

    /**
     * Corresponds to <code>&lt;ant&gt;</code>'s
     * <code>output</code> attribute.
     *
     * @param string $s the filename to write the output to.
     */
    public function setOutput(string $s)
    {
        $this->output = $s;
    }

    /**
     * Corresponds to <code>&lt;ant&gt;</code>'s
     * <code>inheritall</code> attribute.
     *
     * @param b the new value for this boolean flag.
     */
    public function setInheritall(bool $b)
    {
        $this->inheritAll = $b;
    }

    /**
     * Corresponds to <code>&lt;ant&gt;</code>'s
     * <code>inheritrefs</code> attribute.
     *
     * @param b the new value for this boolean flag.
     */
    public function setInheritrefs(bool $b)
    {
        $this->inheritRefs = $b;
    }

    /**
     * Property to pass to the new project.
     * The property is passed as a 'user property'
     */
    public function createProperty()
    {
        $p = new PropertyTask();
        $p->setUserProperty(true);
        $p->setTaskName('property');
        $this->properties[] = $p;

        return $p;
    }

    /**
     * Corresponds to <code>&lt;ant&gt;</code>'s
     * nested <code>&lt;reference&gt;</code> element.
     *
     * @param PhingReference $r the reference to pass on explicitly to the sub-build.
     */
    public function addReference(PhingReference $r)
    {
        $this->references[] = $r;
    }

    /**
     * Set the buildpath to be used to find sub-projects.
     *
     * @param Path $s an Ant Path object containing the buildpath.
     */
    public function setBuildpath(Path $s)
    {
        $this->getBuildpath()->append($s);
    }

    /**
     * Creates a nested build path, and add it to the implicit build path.
     *
     * @return Path the newly created nested build path.
     */
    public function createBuildpath(): Path
    {
        return $this->getBuildpath()->createPath();
    }

    /**
     * Gets the implicit build path, creating it if <code>null</code>.
     *
     * @return Path the implicit build path.
     */
    private function getBuildpath(): Path
    {
        if ($this->buildpath === null) {
            $this->buildpath = new Path($this->getProject());
        }
        return $this->buildpath;
    }

    /**
     * Creates a nested <code>&lt;buildpathelement&gt;</code>,
     * and add it to the implicit build path.
     *
     * @return PathElement the newly created nested build path element.
     */
    public function createBuildpathElement()
    {
        return $this->getBuildpath()->createPathElement();
    }

    /**
     * Buildpath to use, by reference.
     *
     * @param Reference $r a reference to an Ant Path object containing the buildpath.
     */
    public function setBuildpathRef(Reference $r)
    {
        $this->createBuildpath()->setRefid($r);
    }
}
