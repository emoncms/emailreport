<?php global $path, $session; ?>

<br>
<div style="background-color:#fff; padding:20px">

    <h2>Energy Email Reports</h2>
    <p>Receive a weekly email report of home electricity consumption</p>
    <div style="border-bottom:1px solid #ccc"></div><br>
    <input id="weekly" type="checkbox" style="margin-top:-3px" /><div style="display:inline-block; margin-left:5px;">Enable weekly email report</div><br><br>

    Email title:<br>
    <input id="title" style="width:350px"/>
    <br><br>
    
    Email address to send email to:<br>
    <input id="email" style="width:350px"/>
    <br><br>
    
    Select cumulative kwh consumptiion feed: (autoname: use_kwh)<br>
    <select id="feedselect"></select>
    <br><br>
    
    <button class="btn btn-primary" id="save">Save</button> <button class="btn" id="sendtest">Send test email</button> <button class="btn" id="getpreview">Preview</button>
    <br><br>
    <div id="message" class="alert" style="display:none"></div>
</div>
<br>
<div id="preview"></div>

<script>

$("body").css('background-color','#eee');

var path = "<?php echo $path; ?>";

feed_list();
user_get();

$.ajax({ 
    url: path+"emailreport/config", 
    dataType: 'json', 
    async: false, 
    success: function(result){
        config = result;
    }
});

if (config.weekly!=undefined && config.weekly==1) $("#weekly")[0].checked = true;
if (config.title!=undefined) $("#title").val(config.title);
if (config.email!=undefined) $("#email").val(config.email);
if (config.feedid!=undefined) $("#feedselect").val(config.feedid);

preview();

$("#save").click(function(){
    var weekly=0;
    if ($("#weekly")[0].checked) weekly=1;
    
    var title = $("#title").val();
    var email = $("#email").val();
    var feedid = $("#feedselect").val();

    $.ajax({ 
        url: path+"emailreport/save?title="+title+"&weekly="+weekly+"&email="+email+"&feedid="+feedid, 
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
    var title = $("#title").val();
    var email = $("#email").val();
    var feedid = $("#feedselect").val();
    $.ajax({ 
        url: path+"emailreport/sendtest?title="+title+"&email="+email+"&feedid="+feedid,  
        dataType: 'text', 
        async: true, 
        success: function(result){
            $("#message").html(result).show();
        }
    });
});

$("#getpreview").click(function(){ preview(); });
function preview() {
    var title = $("#title").val();
    var feedid = $("#feedselect").val();
    $.ajax({ 
        url: path+"emailreport/preview?title="+title+"&feedid="+feedid, 
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

function feed_list()
{
    var autoid = 0;
    $.ajax({ 
        url: path+"feed/list.json", 
        dataType: 'json', 
        async: false, 
        success: function(feeds){
            var out = "";
            for(var z in feeds) {
                out += "<option value="+feeds[z].id+">"+feeds[z].name+"</option>";
                if (feeds[z].name=="use_kwh") autoid = feeds[z].id;
            }
            $("#feedselect").html(out);
            if (autoid) $("#feedselect").val(autoid);
        }
    });
}

</script>

