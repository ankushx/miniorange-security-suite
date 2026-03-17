require(['jquery', 'jquery/ui'], function($){
    var $m = $.noConflict();
    $m(document).ready(function() {

        $m("#lk_check1").change(function(){
            if($("#lk_check2").is(":checked") && $("#lk_check1").is(":checked")){
                $("#activate_plugin").removeAttr('disabled');
            }
        });

        $m("#lk_check2").change(function(){
            if($("#lk_check2").is(":checked") && $("#lk_check1").is(":checked")){
                $("#activate_plugin").removeAttr('disabled');
            }
        });

        $m(".navbar a").click(function() {
            $id = $m(this).parent().attr('id');
            setactive($id);
            $href = $m(this).data('method');
            voiddisplay($href);
        });
        $m(".btn-link").click(function() {
            $m(this).siblings('.show_info').slideToggle('slow');
            
        });
        $m('#idpguide').on('change', function() {
            var selectedIdp =  jQuery(this).find('option:selected').val();
            $m('#idpsetuplink').css('display','inline');
            $m('#idpsetuplink').attr('href',selectedIdp);
        });
        $m("#mo_saml_add_shortcode").change(function(){
            $m("#mo_saml_add_shortcode_steps").slideToggle("slow");
        });
        $m('#error-cancel').click(function() {
            $error = "";
            $m(".error-msg").css("display", "none");
        });
        $m('#success-cancel').click(function() {
            $success = "";
            $m(".success-msg").css("display", "none");
        });
        $m('#cURL').click(function() {
            $m(".help_trouble").click();
            $m("#cURLfaq").click();
        });
        $m('#help_working_title1').click(function() {
            $m("#help_working_desc1").slideToggle("fast");
        });
        $m('#help_working_title2').click(function() {
            $m("#help_working_desc2").slideToggle("fast");
        });

    });
});

function setactive($id) {
    $m(".navbar-tabs>li").removeClass("active");
    $id = '#' + $id;
    $m($id).addClass("active");
}

function voiddisplay($href) {
    $m(".page").css("display", "none");
    $m($href).css("display", "block");
}

// 2FA Settings Configuration Table functions
function toggleOptions(button) {
    const container = document.querySelector('.tfa-config-table-container');
    if (container && container.dataset.disabled === 'true') {
        return false;
    }
    
    const allOptionsBoxes = document.querySelectorAll('.options-box');

    allOptionsBoxes.forEach(box => {
        const parentButton = box.previousElementSibling;
        if (parentButton !== button) {
            box.classList.add('hidden');
        }
    });

    const optionsBox = button.nextElementSibling;
    if (optionsBox) {
        optionsBox.classList.toggle('hidden');
    }
}

function editRule(role, site, type) {
    const container = document.querySelector('.tfa-config-table-container');
    if (container && container.dataset.disabled === 'true') {
        return false;
    }
    
    const signinsettingsUrl = container ? container.dataset.signinsettingsUrl : '';
    if (!signinsettingsUrl) {
        console.error('Sign in settings URL not found');
        return;
    }
    
    let actionUrl;
    if (type === 'Customer') {
        // For customer rules, pass site and group parameters
        actionUrl = signinsettingsUrl + '?type=customer';
        if (site) {
            actionUrl += '&site=' + encodeURIComponent(site);
        }
        if (role) {
            actionUrl += '&group=' + encodeURIComponent(role);
        }
    } else {
        // For admin rules, pass role parameter
        actionUrl = signinsettingsUrl + '?type=admin';
        if (role) {
            actionUrl += '&role=' + encodeURIComponent(role);
        }
    }

    window.location.href = actionUrl;
}

function deleteRule(formId, role, site, type) {
    const container = document.querySelector('.tfa-config-table-container');
    if (container && container.dataset.disabled === 'true') {
        return false;
    }
    
    const form = document.getElementById(formId);
    if (!form) {
        console.error('Form not found:', formId);
        return;
    }

    // Set the correct option for deletion
    const optionInput = document.createElement('input');
    optionInput.type = 'hidden';
    optionInput.name = 'option';
    optionInput.value = 'delete_existing_rule';
    form.appendChild(optionInput);

    // Create hidden inputs to pass role and type information
    if (type === 'Customer') {
        const deleteRoleInput = document.createElement('input');
        deleteRoleInput.type = 'hidden';
        deleteRoleInput.name = 'delete_role_customer';
        deleteRoleInput.value = role;
        form.appendChild(deleteRoleInput);
    } else {
        const deleteRoleInput = document.createElement('input');
        deleteRoleInput.type = 'hidden';
        deleteRoleInput.name = 'delete_role_admin';
        deleteRoleInput.value = role;
        form.appendChild(deleteRoleInput);
    }

    // Submit the form
    form.submit();
}

// Close options boxes when clicking outside
document.addEventListener('click', function (event) {
    if (!event.target.closest('.three-dots-btn') && !event.target.closest('.options-box')) {
        document.querySelectorAll('.options-box').forEach(box => {
            box.classList.add('hidden');
        });
    }
});

// Custom Gateway Configuration functions
// Toggle functionality for collapsible sections
document.addEventListener("DOMContentLoaded", function () {
    const toggles = document.querySelectorAll(".configuration-toggle");
    toggles.forEach((toggle) => {
        toggle.addEventListener("click", function () {
            const content = this.nextElementSibling;
            const icon = this.querySelector(".toggle-icon");

            if (content.classList.contains("open")) {
                content.classList.remove("open");
                icon.classList.remove("rotate-180");
            } else {
                content.classList.add("open");
                icon.classList.add("rotate-180");
            }
        });
    });
});

function handleSMSGatewayMethodChange() {
    const apiProvider = document.getElementById("api_provider");
    if (!apiProvider) {
        return;
    }
    
    const selectedMethod = apiProvider.value;
    const twilioMethod = document.getElementById("twilio_method");
    const getMethod = document.getElementById("get_method");
    const postMethod = document.getElementById("post_method");

    // Hide all methods first
    [twilioMethod, getMethod, postMethod].forEach(method => {
        if (method) {
            method.classList.add("hidden");
            method.style.setProperty("display", "none", "important");
            const inputs = method.querySelectorAll("input[required]");
            inputs.forEach(input => {
                input.removeAttribute("required");
            });
        }
    });

    switch (selectedMethod) {
        case 'twilio':
            if (twilioMethod) {
                twilioMethod.classList.remove("hidden");
                twilioMethod.style.setProperty("display", "block", "important");
                // Add required to Twilio fields
                const twilioInputs = twilioMethod.querySelectorAll("input");
                twilioInputs.forEach(input => {
                    if (input.name && input.name.includes('twilio')) {
                        input.setAttribute("required", "required");
                    }
                });
            }
            break;
        case 'getMethod':
            if (getMethod) {
                getMethod.classList.remove("hidden");
                getMethod.style.setProperty("display", "block", "important");
                // Add required to Get Method fields
                const getMethodInputs = getMethod.querySelectorAll("input");
                getMethodInputs.forEach(input => {
                    if (input.name && input.name.includes('getmethod')) {
                        input.setAttribute("required", "required");
                    }
                });
            }
            break;
        case 'postMethod':
            if (postMethod) {
                postMethod.classList.remove("hidden");
                postMethod.style.setProperty("display", "block", "important");
                // Add required to Post Method fields
                const postMethodInputs = postMethod.querySelectorAll("input");
                postMethodInputs.forEach(input => {
                    if (input.name && (input.name.includes('postmethod') || input.name.includes('post_method') || input.name.includes('dynamic_attributes'))) {
                        input.setAttribute("required", "required");
                    }
                });
            }
            break;
    }
}

// Make function globally accessible
window.handleSMSGatewayMethodChange = handleSMSGatewayMethodChange;

// Custom Gateway form initialization
document.addEventListener("DOMContentLoaded", () => {
    const forms = document.querySelectorAll('#customGateway_email, #customGateway_sms, #customGateway_email_test, #customGateway_sms_test');
    forms.forEach(form => {
        if (form) {
            form.setAttribute('novalidate', 'novalidate');
        }
    });

    const disabledFields = document.querySelectorAll('#customGateway_email input[disabled], #customGateway_email select[disabled], #customGateway_sms input[disabled], #customGateway_sms select[disabled]');
    disabledFields.forEach(field => {
        if (field.hasAttribute('required')) {
            field.removeAttribute('required');
            field.setAttribute('data-was-required', 'true'); 
        }
    });

    handleSMSGatewayMethodChange();

    document.querySelectorAll(".remove-key").forEach(button => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            const parentDiv = event.target.closest(".flex"); 
            const keyInput = parentDiv.querySelector("input[type='text']:not([disabled])"); 

            if (keyInput) {
                keyInput.value = ""; 
            }
        });
    });

    const addAttrButton = document.getElementById("add_custom_attr");
    const dynamicAttributes = document.getElementById("dynamic_attributes");

    // Only set up post method handlers if elements exist
    if (addAttrButton && dynamicAttributes) {
        let attrIndex = dynamicAttributes.querySelectorAll(".attribute-row").length || 0;

        // Add a new custom attribute
        addAttrButton.addEventListener("click", () => {
            const attrDiv = document.createElement("div");
            attrDiv.className = "flex mt-4 items-center attribute-row";
            attrDiv.setAttribute("data-index", attrIndex);

            const isDisabled = addAttrButton.hasAttribute("disabled");

            // Attribute name input
            const attrNameInput = document.createElement("input");
            attrNameInput.type = "text";
            attrNameInput.placeholder = "Enter Parameter";
            attrNameInput.className = "form-control gm-input w-full";
            attrNameInput.style.width = "57%";
            attrNameInput.name = `dynamic_attributes[${attrIndex}][name]`;
            attrNameInput.required = true;
            if (isDisabled) {
                attrNameInput.disabled = true;
            }

            // Value input
            const attrValueInput = document.createElement("input");
            attrValueInput.type = "text";
            attrValueInput.placeholder = "Enter Value";
            attrValueInput.className = "form-control gm-input ml-4 w-full";
            attrValueInput.style.width = "57%";
            attrValueInput.name = `dynamic_attributes[${attrIndex}][value]`;
            attrValueInput.required = true;
            if (isDisabled) {
                attrValueInput.disabled = true;
            }

            // Remove button
            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.textContent = "-";
            deleteButton.className = "text-white p-2 rounded ml-4";
            deleteButton.style.width = "5rem";
            deleteButton.style.backgroundColor = "#eb5202";
            deleteButton.addEventListener("click", () => {
                attrDiv.remove();
            });

            // Append inputs and button to the row
            attrDiv.appendChild(attrNameInput);
            attrDiv.appendChild(attrValueInput);
            attrDiv.appendChild(deleteButton);

            // Append the row to the dynamic attributes section
            dynamicAttributes.appendChild(attrDiv);

            attrIndex++;
        });

        dynamicAttributes.addEventListener("click", (event) => {
            if (event.target.tagName === "BUTTON" && event.target.textContent === "-") {
                event.target.closest(".attribute-row").remove();
            }
        });
    }
});

function mosp_valid(f) {
    !(/^[a-zA-Z?,.\(\)\/@ 0-9]*$/).test(f.value) ? f.value = f.value.replace(/[^a-zA-Z?,.\(\)\/@ 0-9]/, '') : null;
}


function supportAction(){
}


//update---instead of checkbox using div

var registeredElement = document.getElementById("registered");
if (registeredElement) {
    registeredElement.addEventListener("click", ifUserRegistered, false);
}
function ifUserRegistered() {
  var inputField = document.getElementById("myInput");
  var confirmPasswordElement = jQuery('#confirmPassword');
  var checkAllTopicCheckBoxes = document.getElementById('registered');
  var registerLoginButton = document.getElementById('registerLoginButton');
  var register_login = document.getElementById('register_login');
  const forget=document.getElementById("forget_pass");
  if (confirmPasswordElement.css('display') === 'none') {
    //login time
    confirmPasswordElement.css('display', 'block');
    registerLoginButton.value = "Register"; 
    checkAllTopicCheckBoxes.textContent = 'Already Registered ? Click here to Login';
    register_login.textContent = 'Register with miniOrange';
    confirmPasswordElement.prop('required', false); 
    forget.style.display = 'none';
    inputField.setAttribute("required", "required");
   // inputField.removeAttribute("required");
    
} else {
    //register time
    confirmPasswordElement.css('display', 'none');
    registerLoginButton.value = "Login"; 
    checkAllTopicCheckBoxes.textContent = 'Sign Up';
    register_login.textContent = 'Login with miniOrange';
    confirmPasswordElement.prop('required', true);
    forget.style.display = 'block'; 
    if (inputField.hasAttribute("required")) {
        inputField.removeAttribute("required");
    }
 //   inputField.removeAttribute("required");
   // inputField.setAttribute("required", "required");
  }
}

function kba_div(){
    var element = document.getElementById('hide_show_kba_div');
    var element1 = document.getElementById('avaliable_in_premium_kba');
  if(element.style.display === "none"){
      element.style.display = "block";
      element1.style.display = "block";
  
  }else{
      element.style.display = "none";
      element1.style.display = "none";
  }

    }

    function popup_ui_div(){
        var element = document.getElementById('popup_ui_div');
        var element1 = document.getElementById('avaliable_in_premium_popup');
      
      if(element.style.display === "none"){
          element.style.display = "block";
          element1.style.display = "block";
      
      }else{
          element.style.display = "none";
          element1.style.display = "none";
      }
        }
      
        function customerinline_div(){
         
          var element1 = document.getElementById('avaliable_in_premium_inline');
        if(element1.style.display === "none"){
      
            element1.style.display = "block";
        
        }else{
      
            element1.style.display = "none";
        }
      
          }
      


    function adminrole_method_premium(){
        selectElement = document.querySelector('#twofa_role');
          output = selectElement.value;       
        var element3 = document.getElementById('premium_admin_role');
        if(output=='Administrators'){
          element3.style.display="none";
        }else{
          element3.style.display="block";
        }
      }

      function customGatewayMethod() {
        var x = document.getElementById("customgatewayapiProvidersms").value;
        var a = document.getElementById("twilio_method");
        var b = document.getElementById("get_method");
        var c = document.getElementById("post_method");
      
        if(x=='twilio'){
          a.style.display = "block";
          b.style.display = "none";
          c.style.display = "none";
        }
        if(x=='getMethod'){
          b.style.display = "block";
          a.style.display = "none";
          c.style.display = "none";
        }
        if(x=='postMethod'){
          c.style.display = "block";
          a.style.display = "none";
          b.style.display = "none";
        }
      }
      
        function addCustomAttribute(){
      
          var param = jQuery("#post_parameter").val();
          var val = jQuery("#post_value").val();
          var div = generate(param,val)
           jQuery("#submit_custom_attr").before(div);
           jQuery("#post_parameter").val("");
           jQuery("#post_value").val("");
      
      }
      
      function generate(param,val){
          var attributeDiv =  jQuery("<div>",{"class":"gm-div","style":"margin-top:18px","id":"Div"});
          var labelForAttr = jQuery("<strong>",{"class":"form-control gm-input","style":"margin-left:0px; margin-top:8px;width:185px","type":"text", "placeholder":"Enter name of IDP attribute"}).text(param);
          var inputAttr = jQuery("<input>",{"id":param,"name":param,"class":"form-control gm-input","style":"margin-left:212px; margin-top:8px; position:absolute; padding:7px","type":"text", "placeholder":"Enter name of IDP attribute","value":val});
          attributeDiv.append(labelForAttr);
          attributeDiv.append(inputAttr);
      
          return attributeDiv;
      
      }
      
      function deleteCustomAttribute(){
      
              jQuery("#Div").remove();
      
      }

/**
 * Generic popup management for 2FA settings
 */
function init2FAPopup(type) {
    const prefix = type === 'admin' ? 'Admin' : '';
    const popupId = `popup-alert${prefix ? '-' + type : ''}`;
    const messageId = `popup-message${prefix ? '-' + type : ''}`;
    const pendingVar = `pendingFormSubmission${prefix}`;
    
    // Show popup function
    window[`showPopup${prefix}`] = function(message) {
        const popup = document.getElementById(popupId);
        const popupMessage = document.getElementById(messageId);
        if (popup && popupMessage) {
            popupMessage.innerText = message;
            popup.classList.remove("hidden");
        }
    };
    
    // Close popup function
    window[`closePopup${prefix}`] = function() {
        const popup = document.getElementById(popupId);
        if (popup) {
            popup.classList.add("hidden");
            
            // If there's a pending form submission, execute it now
            if (window[pendingVar]) {
                const { form } = window[pendingVar];
                form.submit();
                window[pendingVar] = null;
            }
        }
    };
}

/**
 * Check if 2FA methods have changed
 */
function haveMethodsChanged2FA(existingRules, newMethods, options = {}) {
    const { siteParam, groupParam, baseWebsiteName } = options;
    
    // For admin: check first rule
    if (!siteParam && !groupParam) {
        if (!existingRules || existingRules.length === 0) {
            return true; // No existing rule
        }
        
        const existingRule = existingRules[0];
        if (!existingRule || !existingRule.methods) {
            return true;
        }
        
        // Check if methods are different
        if (existingRule.methods.length !== newMethods.length) {
            return true;
        }
        
        // Check if any method keys are different
        const existingKeys = existingRule.methods.map(m => {
            if (typeof m === 'string') return m;
            return m.method || m.key || m;
        }).filter(Boolean).sort();
        
        const newKeys = newMethods.map(m => {
            if (typeof m === 'string') return m;
            return m.method || m.key || m;
        }).filter(Boolean).sort();
        
        return JSON.stringify(existingKeys) !== JSON.stringify(newKeys);
    }
    
    // For customer: find rule by site and group
    if (!siteParam || !groupParam || siteParam === "" || groupParam === "") {
        return true; // Not in edit mode
    }
    
    // Find the existing rule for this site+group
    const existingRule = existingRules.find(rule => {
        const ruleSite = rule.site || '';
        const normalizedRuleSite = (ruleSite === 'base' || ruleSite === baseWebsiteName) ? 'base' : ruleSite;
        const normalizedParamSite = (siteParam === 'base' || siteParam === baseWebsiteName) ? 'base' : siteParam;
        return normalizedRuleSite === normalizedParamSite && rule.group === groupParam;
    });
    
    if (!existingRule) {
        return true; // No existing rule
    }
    
    // Check if methods are different
    if (!existingRule.methods || existingRule.methods.length !== newMethods.length) {
        return true;
    }
    
    // Check if any method keys are different
    const existingKeys = existingRule.methods.map(m => m.key || m.method || m).filter(Boolean).sort();
    const newKeys = newMethods.map(m => m.key || m.method || m).filter(Boolean).sort();
    
    return JSON.stringify(existingKeys) !== JSON.stringify(newKeys);
}

/**
 * Initialize 2FA rule management for admin
 */
function initAdmin2FARuleManagement() {
    const container = document.querySelector('[data-2fa-admin-container]');
    if (!container) return;
    
    const addRuleBtn = document.getElementById('addRuleBtn');
    const form = document.getElementById('saveSingInSettings_admin');
    const existingRules = JSON.parse(container.dataset.existingRules || '[]');
    
    if (!addRuleBtn || !form) return;
    
    // Initialize popup
    init2FAPopup('admin');
    
    // Change button text if rule exists
    if (existingRules && existingRules.length > 0) {
        addRuleBtn.value = 'Update Rule';
    }
    
    // Button click handler
    addRuleBtn.addEventListener("click", (event) => {
        event.preventDefault();
        
        // Collect selected OTP methods
        const selectedMethods = [];
        if (document.getElementById('admin_oos')?.checked) selectedMethods.push('OOS');
        if (document.getElementById('admin_ooe')?.checked) selectedMethods.push('OOE');
        if (document.getElementById('admin_oose')?.checked) selectedMethods.push('OOSE');
        if (document.getElementById('admin_googleauthenticator')?.checked) selectedMethods.push('GoogleAuthenticator');
        
        if (selectedMethods.length === 0) {
            window.showPopupAdmin("Please select at least one authentication method.");
            return;
        }
        
        const isUpdateMode = addRuleBtn.value === 'Update Rule';
        
        if (isUpdateMode) {
            if (haveMethodsChanged2FA(existingRules, selectedMethods)) {
                window.pendingFormSubmissionAdmin = { form: form };
                window.showPopupAdmin("Users with 2FA already set up will keep their current settings. To apply new 2FA methods to existing users, please reset their 2FA in User Management. Click OK to continue.");
            } else {
                form.submit();
            }
        } else {
            form.submit();
        }
    });
}

/**
 * Initialize 2FA rule management for customer
 */
function initCustomer2FARuleManagement() {
    const container = document.querySelector('[data-2fa-customer-container]');
    if (!container) return;
    
    const addRuleBtn = document.getElementById('addRuleBtn_customer');
    const form = document.getElementById('saveSingInSettings_customer');
    const existingRules = JSON.parse(container.dataset.existingRules || '[]');
    const siteParam = container.dataset.siteParam || '';
    const groupParam = container.dataset.groupParam || '';
    const baseWebsiteName = container.dataset.baseWebsiteName || '';
    
    if (!addRuleBtn || !form) return;
    
    // Initialize popup
    init2FAPopup('customer');
    
    const isInEditMode = siteParam && groupParam && siteParam !== "" && groupParam !== "";
    
    // Button click handler
    addRuleBtn.addEventListener("click", (event) => {
        event.preventDefault();
        
        // Collect selected OTP methods
        const selectedMethods = Array.from(document.querySelectorAll(".otp-toggle:checked")).map((checkbox) => {
            return {
                key: checkbox.getAttribute("data-method"),
                label: checkbox.getAttribute("data-method-label"),
                icon: checkbox.getAttribute("data-method-icon")
            };
        });
        
        if (!selectedMethods.length) {
            window.showPopup("Please select at least one authentication method.");
            return;
        }
        
        const isUpdateMode = (addRuleBtn.textContent && addRuleBtn.textContent.trim() === 'Update Rule') || 
                              (addRuleBtn.value && addRuleBtn.value === 'Update Rule');
        
        if (isUpdateMode || isInEditMode) {
            if (haveMethodsChanged2FA(existingRules, selectedMethods, { siteParam, groupParam, baseWebsiteName })) {
                window.pendingFormSubmission = { form: form };
                window.showPopup("Users with 2FA already set up will keep their current settings. To apply new 2FA methods to existing users, please reset their 2FA in User Management. Click OK to continue.");
            } else {
                form.submit();
            }
        } else {
            form.submit();
        }
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initAdmin2FARuleManagement();
    initCustomer2FARuleManagement();
});

// 2FA Settings Configuration Table 
require(['jquery'], function($) {
    // Handle mobile dropdown positioning
    function adjustDropdownPosition() {
        if (window.innerWidth <= 768) {
            $('.options-box').each(function() {
                var $dropdown = $(this);
                var $button = $dropdown.siblings('.three-dots-btn');
                if ($button.length === 0) {
                    $button = $dropdown.closest('td').find('.three-dots-btn');
                }
                var buttonOffset = $button.offset();
                var dropdownWidth = $dropdown.outerWidth();
                var windowWidth = $(window).width();
                
                // Check if dropdown would overflow on the right
                if (buttonOffset && (buttonOffset.left + dropdownWidth > windowWidth)) {
                    $dropdown.css({
                        'right': '0',
                        'left': 'auto',
                        'transform': 'none'
                    });
                }
            });
        }
    }
    
    // Adjust on window resize
    $(window).on('resize', function() {
        adjustDropdownPosition();
    });
    
    // Adjust on dropdown open
    $(document).on('click', '.three-dots-btn', function() {
        setTimeout(function() {
            adjustDropdownPosition();
        }, 10);
    });
    
    // Close dropdown when clicking outside on mobile
    $(document).on('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!$(e.target).closest('.three-dots-btn, .options-box').length) {
                $('.options-box').addClass('hidden');
            }
        }
    });
});
      