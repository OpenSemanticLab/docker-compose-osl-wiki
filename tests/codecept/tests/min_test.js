// What is tested in this file?
Feature('min test');
// Login is required for all test scenarios
Before(async ({ I }) => {
    await I.addNotification({ text: "Login first" })
    await I.login()
});
crypto = require('node:crypto');
function uuidv4() {
    return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, c =>
        (+c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> +c / 4).toString(16)
    );
}
////// all scenarios down here ///// 
Scenario('create test article creation @min test', async ({ I }) => {
    // navigate to startpage
    I.amOnPage('/');
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
    // for debugging or subtitleing in tutorial video
    await I.addNotification({ text: `Assign at least a label to your article` })
    // search for formular path using browser inspect tool (F12) and add test value (type as expected in formular field)
    await I.fillEditorField({ schemapath: 'root.label.1.text', value: `Mein Artikel min_test` })
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(3)
});

Scenario('create test person creation @min person', async ({ I }) => {
    // navigate to startpage
    I.amOnPage('/');
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSW44deaa5b806d41a2a88594f562b110e9' }); // Person
    // for debugging or subtitleing in tutorial video 
    await I.addNotification({ text: `Assign at least a label to first name and last name entry` })
    // search for formular path using browser inspect tool (F12) and add test value (type as expected in formular field)
    await I.fillEditorField({ schemapath: 'root.first_name', value: `Max` })
    await I.fillEditorField({ schemapath: 'root.surname', value: `Mustermann` })
    await I.addArrayElement({ schemapath: "root.description" })
    await I.fillEditorField({ schemapath: `root.description.0.text`, value: `Some test description` })
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(3)
});

Scenario('create test room creation @min room', async ({ I }) => {
    // navigate to startpage
    I.amOnPage('/');
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSWc5ed0ed1e33c4b31887c67af25a610c1' }); // Room
    // for debugging or subtitleing in tutorial video 
    await I.addNotification({ text: `Assign at least a label to entry` })
    // search for formular path using browser inspect tool (F12) and add test value (type as expected in formular field)
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: `TestRaum001` })
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(3)
});

Scenario('create test article collection @article collection', async ({ I }) => {
    // navigate to startpage
    I.amOnPage('/');
    const uuid = uuidv4().split('-')[0] + "Meine Artikel Testsammlung" // crypto.randomUUID()
    // find OSW Category
    await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
    // for debugging or subtitleing in tutorial video
    await I.addNotification({ text: `Assign at least a label to your article` })
    // search for formular path using browser inspect tool (F12) and add test value (type as expected in formular field)
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: `Meine Artikel Testsammlung` })
    await I.addArrayElement({ schemapath: "root.description" })
    await I.fillEditorField({ schemapath: `root.description.0.text`, value: `How to create a article collection` })
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(3)

    I.amOnPage('/');
    await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
    // for debugging or subtitleing in tutorial video
    await I.addNotification({ text: `Assign at least a label to your article` })
    // search for formular path using browser inspect tool (F12) and add test value (type as expected in formular field)
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: `Meine Artikel Testsammlung Unterartikel` })
    await I.addArrayElement({ schemapath: "root.description" })
    await I.fillEditorField({ schemapath: `root.description.0.text`, value: `How to link articles in a artcle collection` })
    await I.addAdditionalProperty({ schemapath: "root.part_of" })
    await I.addArrayElement({ schemapath: "root.part_of" })
    await I.fillEditorField({ schemapath: `root.part_of.0`, value: `Meine Artikel Testsammlung` })
    await I.scrollAndMoveAndClick({ selector: '#autocomplete-result-list-4' })
    I.wait(3)
    await I.saveEditor()

    I.amOnPage('/');
    await I.addNotification({ text: "Click on the search icon" })
    await I.scrollAndMoveAndClick({ selector: '#citizen-search__buttonCheckbox' });
    I.wait(1)
    await I.addNotification({ text: "You can search for (parts of) your label" })
    await I.scrollAndMoveAndFillField({ selector: '#searchInput', value: `Meine Artikel Testsammlung` });
    I.wait(2)
    await I.scrollAndMoveAndClick({ selector: '#citizen-typeahead-suggestion-0' })
    // I.see(uuid)
    I.wait(2)
    await I.scrollAndMoveAndClick({ selector: '#page-actions-more__buttonCheckbox' });
    await I.scrollAndMoveAndClick({ selector: '#ca-purge' });
    I.wait(2)
    await I.scrollAndMoveAndClick({ selector: '.fancytree-expander' });
    I.wait(3)
});
