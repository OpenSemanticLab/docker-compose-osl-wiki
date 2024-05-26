Feature('OSL up and running');





Before( async ({ I }) => {
  await I.addNotification({text: "Login first"})
  await I.login()
});

Scenario('check main page', ({ I }) => {
  I.amOnPage('/');
  I.see('Main Page');
});

Scenario('Create ELN entry', async ({ I }) => {
  for (let loop_index = 0; loop_index < 1; loop_index++) {
    I.amOnPage('/');
    await I.openCreateInstanceForm({category: 'Category:OSW0e7fab2262fb4427ad0fa454bc868a0d'}); // ElnEntry

    await I.fillEditorField({name: 'root[label][0][text]', value:'Test label'})

    await I.addAdditionalProperty({name: "Orderer"})
    await I.selectAutocompleteResult({schemapath: 'root.orderer', input:'test', index:0})
    await I.fillEditorField({name: 'root[orderer]', value:''})
    await I.createInline({schemapath: "root.orderer"})
    await I.cancelEditor()
    await I.createInline({schemapath: "root.orderer"})
    let orgName = 'Test Org label ' + loop_index
    await I.fillEditorField({name: 'root[label][0][text]', value: orgName})
    await I.saveEditor()
    await I.assertFieldHasValue({name: "root[orderer]", expected: orgName})

    await I.addAdditionalProperty({name: "Actionees"})
    await I.addArrayElement({schemapath: "root.actionees"})
    await I.createInline({schemapath: "root.actionees.0"})
    let actName = 'Person ' + loop_index
    await I.fillEditorField({name: 'root[first_name]', value: 'Test'})
    await I.fillEditorField({name: 'root[surname]', value: actName})
    await I.saveEditor()
    await I.assertFieldHasValue({name: "root[actionees][0]", expected: 'Test ' + actName})

    //pause()
    await I.saveEditor()
    I.wait(3)

  }
});
