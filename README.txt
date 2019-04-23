## additional instruction for add My payment information to My Account.
1. go to file {ceres/files }/resources/views/MyAccount/MyAccount.twig

after line 96 :
<!-- ./ACCOUNT SETTINGS AREA -->

add this code :
<!-- ./MY PAYMENT INFORMATION -->
<div class="row">
    <div class="col-md-6">
        <h4>My Payment Information</h4>
        <div>
            <div class="cmp cmp-address-list m-b-3">
                <div class="items items-empty">
                    <div class="card"><span class="item-inner"><span class="item-content"><p class="text-muted small font-italic p-t-1"></p></span></span>
                    </div>
                </div>
                <div class="dropdown items" style="display: none;">
                    <div id="bankData" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="dropdown-toggle card"><span class="item-inner"><!----> <span class="item-content"><p class="text-muted small font-italic p-t-1">- Bitte wählen -</p></span></span>
                    </div>
                    <div aria-labelledby="bankData" class="dropdown-menu">
                        <ul></ul>
                    </div>
                </div>
                <div class="add-item"><a href="/my-payment-information" class="btn btn-primary"><i aria-hidden="true" class="fa fa-plus-square"></i>My Payment Information</a></div>
            </div>
        </div>
    </div>
</div>
<!-- ./MY PAYMENT INFORMATION -->