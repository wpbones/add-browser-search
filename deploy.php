<?php

/**
 * This file is included by "bones" command. You'll find
 *
 * $this Instance of bones command console
 * $path Destination path
 *
 * The package is made by `php bones deploy <folder> --wp --pkgm=yarn` (`--no-build`: there is nothing
 * to build). Nothing needs to be added here: bones itself leaves out the tests (tests/,
 * phpunit.xml.dist), the agent instructions (CLAUDE.md, AGENTS.md), README.md, bones, namespace,
 * assets/ and whatever git ignores, and removes Composer's dev packages (PHPUnit, Brain Monkey)
 * from the copy.
 */
