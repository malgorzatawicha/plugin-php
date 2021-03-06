# Introduction

The purpose of this guide is not to cover what Behaviour-driven development (BDD) is. We assume you already have experience with this methodology.

In case you want to have an understanding of what BDD is, you can refer to [Whats In a Story?](http://dannorth.net/whats-in-a-story/) web page.

For our basic usage on BDD, we will test `duckduckgo.com` interface. If you have not yet installed Athena, refer to Athena project page.

# Project Setup
We will create a simple directory structure, which we will use as our workspace.

```
mybdd-tests/
├── Base
│   ├── Context
│   │   └── FeatureContext.php
│   └── Feature
│       └── AnonymousUserSearch.feature
├── Report
├── athena.json
└── behat.yml
```

`athena.json` is where Athena reads the configuration from. In this case it will contain only the necessary information for a simple BDD test run.

`behat.yml` our Behat configuration—with some customizations.

`Report` directory is where the HTML report, generated by Athena, will be output.

`Base\Feature\AnonymousUserSearch.feature` is our story narrative with our different scenarios.

`Base\Context\FeatureContext.php` is where words meet code. Our story implementation.

## The `athena.json`

Athena configuration file is pretty straightforward and requires very little configuration. For now we need only a couple of keys.

### The `selenium.hub_url`

The first thing you specify in `athena.json` file, is the `selenium.hub_url` key. You're telling Athena where the interface for manipulating the browser is located.

```json
{
  "selenium" : {
    "hub_url" : "http://athena-selenium-hub:4444/wd/hub"
  },
```

As you can see `selenium.hub_url` key, is pointing to our—local Selenium set-up.

`selenium-hub` docker container is linked with Athena's PHP container, and is where `athena-selenium-hub` is mapped.

Please refer to [Athena Selenium Plugin](https://athena-oss.github.io/plugin-selenium/) documentation.

### The `report`

One of our goals is to debug what actions were performed by the Browser. We don't need too much detail, but enough to understand what happened, specially in case something goes wrong.

```json
  "report" : {
    "format" : "html",
    "outputDirectory" : "./Report"
  }
}
```

Setting up a report is fairly easy, you just have to define the `report.format`, and `report.outputDirectory`. We will make use of our `./Report` directory to keep things nice and tidy.

## The `behat.yml`

```yaml
# behat.yml
default:
    extensions:
        Athena\Behat\BootstrapFileLoader:
                bootstrap_path: "/opt/athena/bootstrap.php"

        Athena\Event\Proxy\BehatProxyExtension: ~
    suites:
        default:
            paths:
                - %paths.base%/Base/Features
            contexts:
                - Tests\Base\Context\FeatureContext
```

A first look at Behat's configuration file might be scary, although, as you iterate over the different configurations and understand their purpose, everything becomes simple.

### Custom `extensions`

```yaml
        Athena\Behat\BootstrapFileLoader:
                bootstrap_path: "/opt/athena/bootstrap.php"
```

In order to access Athena Programming Interface we need a way to inject its bootstrap (autoloader, etc). That's exactly `BootstrapFileLoader` job.

Athena `php` code is mounted inside a docker container, more specifically in `/opt/athena`. This explains the strange path string you see.

```yaml

        Athena\Event\Proxy\BehatProxyExtension: ~
```

A second look at the extension section makes `BehatProxyExtension` stand-out. For generating reports, we register a listener for each of Behat's events and then convert them in—beautiful HTML markup.

Unfortunately Behat does not provide you a nice interface for registering event listening, unless you create your own extension. Which we did.

### Default Suite

```yaml
    suites:
        default:
            paths:
                - %paths.base%/Base/Features
            contexts:
                - Tests\Base\Context\FeatureContext
```

Behat let's you define different configurations for each test suite, although for the purpose of this guide, we have only the need for a single suite, the default.

The reason for a custom directory structure is: Consistency over our different projects. So far we have adopted directories to start with a capital character, so if we keep working like that, our brain doesn't have to be constantly learning new standards, less distractions.

Our feature files are located inside `Base/Features`, and that's where our configuration file tells Behat to look for `*.feature` files.

When it comes to Context classes, they must be defined in Behat's configuration file as well. For this guide we have a single one, where we will translate our story steps to actual code. I highly recommend you have a good look at good practices and context re-use in Behat's website—after completing this guide.

The namespace used for the Context class will be explained later on.

# Writing a Test

## The Story Telling

```gherkin
Feature: Anonymous User performs a search

  As a Anonymous User
  I want to perform a search for a string
  So that I can get a list of results related with my search

  Scenario: Searched string returns results
    Given the current location is the home page
    When the Anonymous User writes "athena" in the search box
    And the Anonymous User performs a click in the search button
    Then the current location should be results page
    And the results count should be greater than "0"
```

The beauty of story telling is how easily we can understand the whole flow. Just by reading you know, or can imagine, exactly the piece of code to be written.

Something interesting is how easily we can also come up with patterns. Most steps can be converted to use parameters and then re-use these steps. It's an interesting exercise to do—after completing this guide.

Our story file is located at `Base\Feature\AnonymousUserSearch.feature`.

## From Words to Code

```php
<?php

namespace Tests\Base\Context;

use Athena\Test\AthenaTestContext;

/**
 * Features context.
 */
class FeatureContext extends AthenaTestContext
{

}
```

### The Namespace

One of the caveats of writing a test in Athena, is the namespace. It should—always—start with—`Tests\`.
Internally Athena will map `Tests\` to your testing directory.

This behaviour gives you freedom to choose how you organise the directory, where you store your tests. 

### The Parent Class

Another close look to the code will make our parent class, `AthenaTestContext` stand-out, and you'll ask yourself what it does, if you didn't, you have now. When building our tests we should—always—include Athena's test cases.

Each type of test is wrapped with a Athena class of it's own, as you can imagine, this introduces custom behaviour when needed. I won't cover here all the types, as the focus is our BDD test case. After completing the guide, I recommend you giving them a—quick—look.

### The Browser Navigation

```php
    /**
     * @var \Athena\Browser\Page\PageInterface
     */
    private $currentLocation;

    /**
     * @Given /^the current location is home page$/
     */
    public function theCurrentLocationIsHomePage()
    {
        $this->currentLocation = Athena::browser()->get('https://duckduckgo.com');
    }
```

Our first step expects our current location to be the homepage. That's exactly what we do when calling `Athena::browser()->get('https://duckduckgo.com')`, our browser will navigate to the given address and return an interface to act upon the page.

Important note is `Athena::browser()` returns always the current active Browser instance.

### The Interaction With Elements

```php
    /**
     * @When /^the Anonymous User writes "([^"]*)" in the search box$/
     */
    public function theAnonymousUserWritesInTheSearchBox($arg1)
    {
        $this->currentLocation
            ->find()
            ->elementWithName('q')
            ->sendKeys($arg1);
    }

    /**
     * @Given /^the Anonymous User performs a click in the search button$/
     */
    public function theAnonymousUserPerformsAClickInTheSearchButton()
    {
        $this->currentLocation
            ->find()
            ->elementWithId('search_button_homepage')
            ->click();
    }
```

Next two steps make use of `PageFinderInterface` to search for our elements and act on them. We simply want to search and perform basic actions, although you can assert that elements meet a certain criteria.

### Out Of The Ordinary Assertions

```php
    /**
     * @Then /^the current location should be results page$/
     */
    public function theCurrentLocationShouldBeResultsPage()
    {
        PHPUnit_Framework_Assert::assertStringMatchesFormat("https://duckduckgo.com/?q=athena", Athena::browser()->getCurrentURL());
    }

    /**
     * @Given /^the results count should be greater than "([^"]*)"$/
     */
    public function theResultsCountShouldBeBiggerThan($arg1)
    {
        $results = $this->currentLocation
            ->find()
            ->elementsWithCss('.result');

        PHPUnit_Framework_Assert::assertGreaterThan($arg1, count($results));
    }
```

Some assertions are not yet covered by Athena programming interface, although on out of the ordinary situations you can take advantage of underlaying technologies, if it does—not—compromise code structure or introduce more complexity and confusion.

### All Pieces Together

Our `FeatureContext.php` file is now finished.

```php
<?php

namespace Tests\Base\Context;

use Athena\Athena;
use Athena\Test\AthenaTestContext;
use PHPUnit_Framework_Assert;

/**
 * Features context.
 */
class FeatureContext extends AthenaTestContext
{
    /**
     * @var \Athena\Browser\Page\PageInterface
     */
    private $currentLocation;

    /**
     * @Given /^the current location is the home page$/
     */
    public function theCurrentLocationIsTheHomePage()
    {
        $this->currentLocation = Athena::browser()->get('https://duckduckgo.com/');
    }

    /**
     * @When /^the Anonymous User writes "([^"]*)" in the search box$/
     */
    public function theAnonymousUserWritesInTheSearchBox($arg1)
    {
        $this->currentLocation
            ->find()
            ->elementWithName('q')
            ->sendKeys($arg1);
    }

    /**
     * @Given /^the Anonymous User performs a click in the search button$/
     */
    public function theAnonymousUserPerformsAClickInTheSearchButton()
    {
        $this->currentLocation
            ->find()
            ->elementWithId('search_button_homepage')
            ->click();
    }

    /**
     * @Then /^the current location should be results page$/
     */
    public function theCurrentLocationShouldBeResultsPage()
    {
        PHPUnit_Framework_Assert::assertStringMatchesFormat("https://duckduckgo.com/?q=athena", Athena::browser()->getCurrentURL());
    }

    /**
     * @Given /^the results count should be greater than "([^"]*)"$/
     */
    public function theResultsCountShouldBeBiggerThan($arg1)
    {
        $results = $this->currentLocation
            ->find()
            ->elementsWithCss('.result');

        PHPUnit_Framework_Assert::assertGreaterThan($arg1, count($results));
    }
}
```

# Execute The Test

Athena runs it's tests through the command line interface, so we'll need to navigate inside Athena's project directory, to access it's executable.

```
$ athena php bdd
...

usage: athena php bdd <tests-directory> <config-file> [<options>...] [<behat-options>...]

    <tests-directory>                   This directory will be mounted inside the docker container. Behat will be executed inside this directory
    <config-file>                       Athena config file, with proxy configurations, grid options, etc
    [--browser=<name>]                  Browser name to be used. Such as firefox, phantomjs, or chrome
    [--parallel-process=<number>]       Number of scenarios, of a single feature, to be ran in parallel
    [--parallel-features=<number>]      Number of features to be ran in parallel. This can be used with --parallel-process to achieve the best results
    [--php-version=<version>]           Switch between available PHP versions. E.g. --php-version=7.0
    [--override-athena-dependencies]    Override PHP plugin dependencies with the ones found inside the tests directory
    [--restore-athena-dependencies]     Restore PHP plugin original dependencies
```

Writing `athena php bdd` and hitting enter, will show you the basic usage, on the requirements to run a bdd test case. Most likely by now you already know the next steps.

```
$ athena php bdd ../mybdd-tests ../mybdd-tests/athena.json --browser=firefox
```

Once you run that command, if it is your first time running athena, you'll most likely see a lot of output. This is Athena setting up it's docker images.

When it's all completed, or you are running the command a second time, after having everything installed, you should see the following:

```
...

Feature: Anonymous User performs a search

  As a Anonymous User
  I want to perform a search for a string
  So that I can get a list of results related with my search

  Scenario: Searched string returns results
    Given the current location is the home page
    When the Anonymous User writes "athena" in the search box
    And the Anonymous User performs a click in the search button
    Then the current location should be results page
    And the results count should be greater than "0"

1 scenario (1 passed)
5 steps (5 passed)
0m6.86s (14.83Mb)
```

# Execute a Single Feature

Sometimes during development time we need to run a single test. There are two ways we can do that, either through tagging (read more about this in Behat's documentation page) the test or take advantage of Athena CLI interface.

```
$ athena php bdd ../mybdd-tests ../mybdd-tests/athena.json --browser=firefox Base/Feature/AnonymousUserSearch.feature
```

Behat will construct the path to the feature relative to `behat.yml` location. That's why we start at `Base\`, since our `behat.yml` is located at the same directory level.

# Reading The Report

In our configuration file, we've specified `Report/` as our output directory for the report file, so that where we will be looking for it.

```
mybdd-tests/Report
├── athenaimg_56d5bd53cfc79.jpg
├── athenaimg_56d5bd5460f48.jpg
├── athenaimg_56d5bd56ce02e.jpg
├── athenaimg_56d5bd575a8d6.jpg
├── athenaimg_56d5bd57ed91d.jpg
└── report.html
```

Open `report.html` in your browser, and you should see nice HTML report containing all the steps we took, together with screenshots for each one.

# Configure Proxy and/or Grid Hub

When `athena php bdd` is run, it will try to automatically link with a running Proxy Server (`athena proxy start`).

If you specify `--browser`, it will try to automatically link with a running Grid Hub (`athena selenium start hub`).

In case `--skip-proxy` or/and `--skip-hub` exists, the link will not be performed.

For performing a link with another running container, you can optionally specify `--link-proxy=<container_name>` and/or `--link-hub=<container_name>`.

# Parallel Tests

```bash
$ athena php bdd my-tests/ my-tests/athena.json [--browser=<name>] --parallel-features=<number> --parallel-process=<number>
```

## Features in Parallel

```bash
$ athena php bdd example-tests/ example-tests/athena.json --parallel-features=2
```

## Scenarios in Parallel

```bash
$ athena php bdd example-tests/ example-tests/athena.json --parallel-process=2
```

## Features and Scenarios in Parallel

In this example, will run two features in parallel, and each feature, will run two scenarios in parallel.

```bash
$ athena php bdd example-tests/ example-tests/athena.json --parallel-features=2 --parallel-process=2
```
