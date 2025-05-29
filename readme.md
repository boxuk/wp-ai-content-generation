# WordPress AI Content Generation

This proof of concept aims to provide a method for users to have rich, block-based content generation from an AI by providing the relevant structure and framework to provide the data needed for both the AI to select a component to use, and the correct format for WP to parse the content and generate the blocks in the editor as needed. 

## Getting Started

As a pre-requisite, you'll require **Node** and **Docker** to work on this project. The project is powered by `@wordpress/env` ([docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)) and `@wordpress/scripts` ([docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)) for compiling and providing a working environment.

```bash
npm install # Install Deps
npm run env start # Start the environment
npm run composer install # Install Composer deps
npm run start # Start the build/watcher
```

This will get you a local WP environemnt and start the build watcher. To stop the environment run `npm run env stop`, or check out the docs for more things you can do to manage the build process or your environment. There's _lots_ of options in the docs, so worth checking out if you're unsure how to access things like logs or to connect xdebug. 

You'll also need to setup your local `.env` file, baed on the `.env.dist` file. This file should include an OpenAI API key. This should not be committed to the repository. 

## Testing The Content Generator

Instead of loading the whole editor, you can also just test the AI part of the process by running the following command. 

```sh
npm run wp wp-ai-content-generation
```

This will output the array of blocks expected to be generated.

## Working with the Content Generator

The content generation uses Action Scheduler to handle making multiple AI requests asynchronously since each request is likely to timeout. Action Scheduler doesn't always work well on local setups due to the lack of traffic. Because of this, you can run action scheduler workers manually by calling 

```sh
npm run wp action-scheduler run
```

## Running Commands

You can use your host `npm` to manage node packages. All other commands can be run inside the docker container by following [this documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-composer-phpunit-and-wp-cli-tools). As a convenience, you can run `composer` by running `npm run composer`, followed by any arguments. For named arguments, you'll need to use double-double-dashes to pass the named argument to the running comand so it's not interpreted by NPM or wp-env. ie:

```bash
npm run composer install -- --no-dev
```

Typically I'd suggest running any PHP related commands as composer scripts to resolve ambiguity. You can also setup node package scripts to reference the composer scripts, allowing you to run single commands easily. Take a look at `npm run lint:php` for example. 

## Next Steps

There's plenty of ways we can improve this. A few ideas that we've come up with include: 
 - [X] Dynamic Schema based on Blocks. 
     - [X] OpenAI allows you to define a Schema for the response, but at the moment our schema is too 'open' and allows invalid responses. We should create the schema based on the registered blocks in the registry. 
 - [ ] Provide additional context to improve the quality of the results. 
     - [ ] We could provide samples of existing pages to provide additional context on suggested layouts and tone of voice
     - [ ] We could schedule a background task to analyse all the current content on the site to identify tone of voice, and include that tone of voice in future request. 
     - [ ] We could schedule a background task to analyse all the current content to indentify common design patterns, such as commonly used block layouts, and provide that as a set of recommended patters in the response. 
 - [ ] Fix some bugs/issues
     - [ ] The 'preview' iframe does not include block styles.
     - [ ] Errors and fail states aren't handled well. 
     - [ ] There's no testing
     - [ ] There's little abstraction
     - [ ] Improve the code structure to allow selecting different models and/or differnt providers through a common interface
