// What is tested in this file?
Feature('article collection');
// Login is required for all test scenarios
Before(async ({ I }) => {
    await I.addNotification({ text: "Login first" })
    await I.login()
});

Scenario('create test article collection @article collection', async ({ I }) => {
    // navigate to startpage
    I.amOnPage('/');
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
    // for debugging or subtitleing in tutorial video
    await I.addNotification({ text: `Assign at least a label to your article` })
    // create article
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: `My article collection` })
    await I.addArrayElement({ schemapath: "root.description" })
    // add description
    await I.fillEditorField({ schemapath: `root.description.0.text`, value: `How to create an article collection` })
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(2)

    // navigate to startpage
    I.amOnPage('/');
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
    // for debugging or subtitleing in tutorial video
    await I.addNotification({ text: `Assign at least a label to your article` })
    // create subarticle
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: `My article collection - subarticle` })
    await I.addArrayElement({ schemapath: "root.description" })
    // add description
    await I.fillEditorField({ schemapath: `root.description.0.text`, value: `How to link articles in an article collection` })
    // add property article collection
    await I.addAdditionalProperty({ schemapath: "root.part_of" })
    await I.addArrayElement({ schemapath: "root.part_of" })
    await I.addNotification({ text: "Select the main article" })
    // link to first article created
    await I.fillEditorField({ schemapath: `root.part_of.0`, value: `My article collection` })
    I.wait(2)
    // select correct article from suggestions list
    await I.scrollAndMoveAndClick({ selector: '#autocomplete-result-list-4' })
    // click save-button
    await I.saveEditor()

    // navigate to startpage
    I.amOnPage('/');
    await I.addNotification({ text: "Click on the search icon" })
    // click search button on main page
    await I.scrollAndMoveAndClick({ selector: '#citizen-search__buttonCheckbox' });
    I.wait(2)
    // search for article
    await I.addNotification({ text: "You can search for (parts of) your label" })
    await I.scrollAndMoveAndFillField({ selector: '#searchInput', value: `My article collection` });
    I.wait(2)
    // select correct article from suggestions list
    await I.scrollAndMoveAndClick({ selector: '#citizen-typeahead-suggestion-0' })
    I.wait(2)
    // expand folder structure to see linked articles (my article collection - subarticle)
    await I.scrollAndMoveAndClick({ selector: '.fancytree-expander' });
    I.wait(2)
});
