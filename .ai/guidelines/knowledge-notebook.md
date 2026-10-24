# Knowledge NoteBook
The knowledge notebook is a skill and is intended for saving the project features information.
You must always create a knowledge base for yourself, after each task you do. The knowledge base must contain the information about the website and its different parts and features.
After implementing a task successfully you must summarize the feature that you implemented and reference the implemented routes of the website.
You should briefly explain each feature that you implement so that later you can use them as a skill.
You must always consult the knowledge notebook before performing any action to know how the website works.

## Knowledge Notebook Location
The knowledge notebook `SKILL.md` file is located at the `.ai/skills/knowledge-notebook/SKILL.md` path.
The knowledge files about different features of the website is located at the `.ai/skills/knowledge-notebook/features` directory.

## Feature Files Format
Each feature file at the `.ai/skills/knowledge-notebook/feature` path must contain an explanation about how the feature works and its related routes, controller, views, model, and service.

## `SKILL.md` File
The SKILL file must briefly explain about each feature and reference the feature file.

## Example
For example, you can check the `.agents/skills/laravel-best-practices` structure. Its structure is similar to the knowledge notebook. There is a unit `.agents/skills/laravel-best-practices/SKILL.md` file that references the different rules
in the `.agents/skills/laravel-best-practices/rules` directory, and briefly explains each rule.

## Action
After Executing each task, You must update and modify the knowledge notebook skill. If you implemented a new feature you must add a `{feature_name}.md` file to the `.ai/skills/knowledge-notebook/features` directory and fill it with 
information about the feature. Then briefly explain the feature in `.ai/skills/knowledge-notebook/SKILL.md` and reference it to the `.ai/skills/knowledge-notebook/features/{feature_name}` file.
