<?php 
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $path;
?>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>
<br>
<div id="emailreport-app">

<div style="background-color:#fff; padding:20px">

    <h2>Energy Email Reports</h2>
    <p>Receive a weekly email report of home electricity consumption</p>
    <div style="border-bottom:1px solid #ccc"></div><br>

    Select report:<br>
    <select v-model="report" @change="onReportChange">
        <?php foreach ($reportlabels as $key => $label) { ?>
            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php } ?>
    </select><br><br>

    <div id="emailreport-config">
        <div v-for="(option, key) in configOptions" :key="key" style="margin-bottom:12px">
            <template v-if="option.type==='checkbox'">
                <input type="checkbox" :id="key" style="margin-top:-3px" v-model="config[key]" true-value="1" false-value="0" />
                <div style="display:inline-block; margin-left:5px">{{ option.description }}</div>
            </template>

            <template v-else-if="option.type==='text' || option.type==='email'">
                <span>{{ option.description }}</span><br>
                <input type="text" :id="key" style="width:350px" v-model="config[key]" />
            </template>

            <template v-else-if="option.type==='feedselect'">
                <span>{{ option.description }} (autoname: {{ option.autoname }})</span><br>
                <select :id="key" v-model="config[key]">
                    <option v-for="feed in feedList" :key="feed.id" :value="String(feed.id)">{{ feed.name }}</option>
                </select>
            </template>
        </div>
    </div>
    
    <button class="btn btn-primary" @click="save">Save</button>
    <button class="btn" @click="sendtest">Send test email</button>
    <br><br>
    <div v-if="message" class="alert">{{ message }}</div>
</div>
<br>
<div id="preview" v-html="previewHtml"></div>

</div>

<script>

document.body.style.backgroundColor = "#eee";

new Vue({
    el: "#emailreport-app",
    data: {
        emailreports: <?php echo json_encode($emailreports); ?>,
        report: "",
        config: {},
        feedList: [],
        feedsByName: {},
        message: "",
        previewHtml: "",
        previewTimer: null,
        suspendAutoPreview: false,
        lastPreviewComparable: ""
    },
    computed: {
        configOptions: function () {
            return this.emailreports[this.report] || {};
        }
    },
    watch: {
        config: {
            deep: true,
            handler: function () {
                if (this.suspendAutoPreview) return;
                var comparable = this.getComparableConfigString();
                if (comparable === this.lastPreviewComparable) return;
                this.lastPreviewComparable = comparable;
                this.schedulePreview();
            }
        }
    },
    mounted: function () {
        var reportKeys = Object.keys(this.emailreports || {});
        if (reportKeys.length > 0) {
            this.report = reportKeys[0];
            this.loadFeeds();
            this.loadConfigView();
        }
    },
    methods: {
        onReportChange: function () {
            this.loadConfigView();
        },
        buildDefaultConfig: function () {
            var defaults = {};
            for (var key in this.configOptions) {
                if (!this.configOptions.hasOwnProperty(key)) continue;
                var type = this.configOptions[key].type;
                if (type === "checkbox") defaults[key] = 0;
                else defaults[key] = "";
            }
            return defaults;
        },
        fetchJSON: function (url) {
            return fetch(url, { credentials: "same-origin" }).then(function (response) {
                return response.json();
            });
        },
        fetchText: function (url) {
            return fetch(url, { credentials: "same-origin" }).then(function (response) {
                return response.text();
            });
        },
        loadConfigView: function () {
            var self = this;
            this.message = "";
            this.suspendAutoPreview = true;
            this.fetchJSON(path + "emailreport/config?report=" + encodeURIComponent(this.report))
                .then(function (result) {
                    var loaded = self.buildDefaultConfig();
                    if (result && typeof result === "object") {
                        for (var key in result) {
                            if (result.hasOwnProperty(key)) loaded[key] = result[key];
                        }
                    }

                    for (var optionKey in self.configOptions) {
                        if (!self.configOptions.hasOwnProperty(optionKey)) continue;
                        var option = self.configOptions[optionKey];
                        if (option.type === "checkbox") {
                            loaded[optionKey] = Number(loaded[optionKey]) === 1 ? 1 : 0;
                        }
                        if (option.type === "feedselect") {
                            if ((loaded[optionKey] === "" || loaded[optionKey] === undefined) && self.feedsByName[option.autoname] !== undefined) {
                                loaded[optionKey] = String(self.feedsByName[option.autoname].id);
                            } else if (loaded[optionKey] !== undefined) {
                                loaded[optionKey] = String(loaded[optionKey]);
                            }
                        }
                    }

                    self.config = loaded;
                    return self.userGet();
                })
                .then(function () {
                    self.suspendAutoPreview = false;
                    self.lastPreviewComparable = self.getComparableConfigString();
                    self.schedulePreview();
                })
                .catch(function () {
                    self.suspendAutoPreview = false;
                });
        },
        getComparableConfigString: function () {
            var filtered = {};
            for (var key in this.config) {
                if (!this.config.hasOwnProperty(key)) continue;
                if (key === "enable" || key === "email") continue;
                filtered[key] = this.config[key];
            }
            return JSON.stringify(filtered);
        },
        schedulePreview: function () {
            var self = this;
            if (this.previewTimer !== null) {
                clearTimeout(this.previewTimer);
            }
            this.previewTimer = setTimeout(function () {
                self.preview();
                self.previewTimer = null;
            }, 100);
        },
        loadFeeds: function () {
            var self = this;
            this.fetchJSON(path + "feed/list.json").then(function (result) {
                self.feedList = Array.isArray(result) ? result : [];
                var byName = {};
                for (var i = 0; i < self.feedList.length; i++) {
                    byName[self.feedList[i].name] = self.feedList[i];
                }
                self.feedsByName = byName;
            });
        },
        save: function () {
            var self = this;
            var url = path + "emailreport/save?report=" + encodeURIComponent(this.report) + "&config=" + encodeURIComponent(JSON.stringify(this.config));
            this.fetchText(url).then(function (raw) {
                var parsed = null;
                try {
                    parsed = JSON.parse(raw);
                } catch (e) {
                    parsed = raw;
                }

                if (parsed && parsed.success !== undefined && parsed.success) {
                    self.message = "Config saved";
                } else {
                    self.message = typeof parsed === "string" ? parsed : JSON.stringify(parsed);
                }
            });
        },
        preview: function () {
            var self = this;
            var url = path + "emailreport/preview?report=" + encodeURIComponent(this.report) + "&config=" + encodeURIComponent(JSON.stringify(this.config));
            this.fetchText(url).then(function (result) {
                self.previewHtml = result;
                self.$nextTick(function () {
                    var emailouter = document.getElementById("emailouter");
                    if (emailouter) emailouter.style.padding = "0px";
                });
            });
        },
        sendtest: function () {
            var self = this;
            var url = path + "emailreport/preview/sendtest?report=" + encodeURIComponent(this.report) + "&config=" + encodeURIComponent(JSON.stringify(this.config));
            this.fetchText(url).then(function (result) {
                self.message = result;
            });
        },
        userGet: function () {
            var self = this;
            return this.fetchJSON(path + "user/get.json").then(function (result) {
                if (self.configOptions.email && result && result.email !== undefined) {
                    self.$set(self.config, "email", result.email);
                }
            });
        }
    }
});

</script>

