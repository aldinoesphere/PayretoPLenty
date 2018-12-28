<div class="row">
    <div class="col-md-6">
        <h4>
            Bankdaten
        </h4>
        <p class="text-muted small font-italic">Ändern Sie Ihre Zahlungsinformationen</p>
        <div>
            <div class="cmp cmp-address-list m-b-3">
                <div class="items items-empty">
                    <div class="card"><span class="item-inner"><span class="item-content"><p class="text-muted small font-italic p-t-1">- Noch keine Bankdaten vorhanden -</p></span></span>
                    </div>
                </div>
                <div class="dropdown items" style="display: none;">
                    <div id="bankData" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="dropdown-toggle card"><span class="item-inner"><!----> <span class="item-content"><p class="text-muted small font-italic p-t-1">- Bitte wählen -</p></span></span>
                    </div>
                    <div aria-labelledby="bankData" class="dropdown-menu">
                        <ul></ul>
                    </div>
                </div>
                <div class="add-item">
                    <button href="#" class="btn btn-primary"><i aria-hidden="true" class="fa fa-plus-square"></i> Bankdaten hinzufügen
                    </button>
                </div>
            </div>
            <div>
                <div tabindex="-1" role="dialog" class="modal fade">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">×</span></button>
                                <h4 class="modal-title"></h4></div>
                            <div class="modal-body">
                                <fieldset>
                                    <form id="my-bankForm">
                                        <div class="row">
                                            <div class="col-xs-12">
                                                <div data-validate="text" class="input-unit accountOwner">
                                                    <input type="text" name="kontoinhaber" data-autofocus="">
                                                    <label for="txt_firma24">Kontoinhaber*</label>
                                                </div>
                                            </div>
                                            <div class="col-xs-12">
                                                <div class="input-unit">
                                                    <input type="text" name="kreditInstitut">
                                                    <label for="txt_firma24">Kreditinstitut</label>
                                                </div>
                                            </div>
                                            <div class="col-xs-12">
                                                <div data-validate="regex" class="input-unit iban">
                                                    <input type="text" name="iban" data-validate-ref="/[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}/" maxlength="32">
                                                    <label for="txt_firma24">IBAN*</label>
                                                </div>
                                            </div>
                                            <div class="col-xs-12">
                                                <div data-validate="regex" class="input-unit no-bottom">
                                                    <input type="text" name="bic" data-validate-ref="/([a-zA-Z]{4}[a-zA-Z]{2}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?)/">
                                                    <label for="txt_firma24">BIC* - Bitte BIC bei Auslandsüberweisungen angeben</label>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </fieldset>
                            </div>
                            <div class="modal-footer">
                                <div class="row">
                                    <div class="col-xs-12">
                                        <button type="button" class="btn btn-primary btn-block">Speichern</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div tabindex="-1" role="dialog" class="modal fade">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" aria-label="Close" class="close"><span aria-hidden="true">×</span></button>
                                <h4 class="modal-title">Bankdaten löschen</h4></div>
                            <div class="modal-body">
                                <fieldset>
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <label>Bankdaten wirklich löschen?</label>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-3 offset-sm-6">
                                            <button type="button" class="btn btn-primary btn-block">Abbrechen</button>
                                        </div>
                                        <div class="col-sm-3">
                                            <button type="button" class="btn btn-primary btn-block">Löschen</button>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>