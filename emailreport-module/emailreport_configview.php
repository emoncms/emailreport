<?php global $path, $session; ?>

<br>
<div style="background-color:#fff; padding:20px">

    <h2>Energy Email Reports</h2>
    <p>Receive a weekly email report of home electricity consumption</p>
    <div style="border-bottom:1px solid #ccc"></div><br>

    <div id="emailreport-config"></div>
    
    <button class="btn btn-primary" id="save">Save</button> <button class="btn" id="sendtest">Send test email</button> <button class="btn" id="getpreview">Preview</button>
    <br><br>
    <div id="message" class="alert" style="display:none"></div>
</div>
<br>
<div id="preview"></div>

<script>

$("body").css('background-color','#eee');
var path = "<?php echo $path; ?>";
var emailreports = <?php echo json_encode($emailreports); ?>;
// -----------------------------------------------------------------------------------------------------------------
// 1) Config options to draw config view
// -----------------------------------------------------------------------------------------------------------------
var report = "home-energy";
var config_options = emailreports[report];

// -----------------------------------------------------------------------------------------------------------------
// 2) Draw the config view
// -----------------------------------------------------------------------------------------------------------------
var out = "";
for (var key in config_options) {

    // Input type checkbox
    if (config_options[key].type=="checkbox") {
        out += "<input type='checkbox' key='"+key+"' style='margin-top:-3px' />";
        out += "<div style='display:inline-block; margin-left:5px;'>"+config_options[key].description+"</div><br>";
    }
        
    // Input type text
    if (config_options[key].type=="text" || config_options[key].type=="email") {
        out += ""+config_options[key].description+"<br>";
        out += "<input type='text' key='"+key+"' style='width:350px'/><br>";
    }
    
    // Input type feedselect
    if (config_options[key].type=="feedselect") {
        out += "<p>"+config_options[key].description+" (autoname: "+config_options[key].autoname+")<br>";
        out += "<select class='feedselect' key='"+key+"'></select>";
    }
    
    out += "<br>";
}
$("#emailreport-config").html(out);

// -----------------------------------------------------------------------------------------------------------------
// 3) Load user configuration
// -----------------------------------------------------------------------------------------------------------------
var config = {};
$.ajax({ 
    url: path+"emailreport/config?report="+report, 
    dataType: 'json', async: false, 
    success: function(result) { config = result; }
});

// -----------------------------------------------------------------------------------------------------------------
// 4) Populate inputs
// -----------------------------------------------------------------------------------------------------------------
load_feeds();

for (var key in config_options) {

    if (config_options[key].type=="checkbox") {
        if (config[key]!=undefined && config[key]==1) $("input[key='"+key+"']")[0].checked = true;
    }
    
    if (config_options[key].type=="text" || config_options[key].type=="email") {
        if (config[key]!=undefined) $("input[key='"+key+"']").val(config[key]);
    }

    if (config_options[key].type=="feedselect") {
        var autoname = config_options[key].autoname;
        $(".feedselect[key="+key+"]").val(feeds[autoname].id);
        if (config[key]!=undefined) $(".feedselect[key="+key+"]").val(config[key]);
    }
}

// -----------------------------------------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------------------------------------

user_get();

preview();

$("#save").click(function(){
    
    fetch_config_from_inputs();

    $.ajax({ 
        url: path+"emailreport/save?report="+report+"&config="+JSON.stringify(config),
        dataType: 'json', 
        async: true, 
        success: function(result){
            if (result.success!=undefined && result.success) {
                $("#message").html("Config saved").show();
            } else {
                $("#message").html(result).show();
            }
        }
    });
});

$("#sendtest").click(function(){

    fetch_config_from_inputs();
    
    $.ajax({ 
        url: path+"emailreport/sendtest?report="+report+"&config="+JSON.stringify(config),
        dataType: 'text', 
        async: true, 
        success: function(result){
            $("#message").html(result).show();
        }
    });
});

$("#getpreview").click(function(){ preview(); });

function preview() {

    fetch_config_from_inputs();
    
    $.ajax({ 
        url: path+"emailreport/preview?report="+report+"&config="+JSON.stringify(config),
        dataType: 'text', 
        async: true, 
        success: function(result){
            $("#preview").html(result).show();
            $("#emailouter").css("padding","0px");
        }
    });
}

function user_get()
{
    $.ajax({ 
        url: path+"user/get.json", 
        dataType: 'json', 
        async: true, 
        success: function(result){
            $("#email").val(result.email);
        }
    });
}

function load_feeds()
{
    feeds = {};
    $.ajax({ 
        url: path+"feed/list.json", 
        dataType: 'json', 
        async: false, 
        success: function(result){
            for(var z in result) {
                feeds[result[z].name] = result[z];
            }
        }
    });
    
    var out = "";
    for(var z in feeds)
        out += "<option value="+feeds[z].id+">"+feeds[z].name+"</option>";
    $(".feedselect").html(out);
}

function fetch_config_from_inputs() {
    config = {};
    for (var key in config_options) {
        if (config_options[key].type=="checkbox") {
            config[key] = 0;
            if ($("input[key='"+key+"']")[0].checked) config[key]=1;
        }
        if (config_options[key].type=="text" || config_options[key].type=="email") config[key] = $("input[key='"+key+"']").val();
        if (config_options[key].type=="feedselect") config[key] = $(".feedselect[key="+key+"]").val();
    }
}

</script>

