// What is tested in this file?
Feature('min test');
// Login is required for all test scenarios
Before(async ({ I }) => {
    await I.addNotification({ text: "Login first" })
    await I.login()
});
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
    // click save-button
    await I.saveEditor()
    // wait 3 seconds to show video content
    I.wait(3)
});