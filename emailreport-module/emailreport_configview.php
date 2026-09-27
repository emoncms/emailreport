<?php 
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $path;
load_js("Lib/js/vue.global.prod-3.5.22.min.js");
load_css("Modules/emailreport/emailreport_view.css");
?>

<div class="page-header">
    <h3>Energy Email Reports</h3>
</div>

<div id="emailreport-app" class="panel-page emailreport-page" v-cloak>
    <p class="page-lead">Receive a weekly email report of home electricity consumption.</p>

    <ul class="nav nav-tabs er-tabs">
        <?php foreach ($reportlabels as $key => $label) { $k = htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>
        <li class="nav-item"><a class="nav-link" :class="{active: report == '<?php echo $k; ?>'}" href="#" @click.prevent="setReport('<?php echo $k; ?>')"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a></li>
        <?php } ?>
    </ul>

    <div class="panel">
        <div class="panel-header panel-header-static">
            <span class="panel-accent"></span>
            <span class="panel-name">Settings</span>
            <span class="panel-badge">{{ config.enable == 1 ? 'Enabled' : 'Off' }}</span>
        </div>
        <div class="panel-body panel-form">
            <div v-for="(option, key) in configOptions" :key="key" class="panel-field">
                <div v-if="option.type==='checkbox'" class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" :id="'er-' + key" v-model="config[key]" true-value="1" false-value="0" />
                    <label class="form-check-label" :for="'er-' + key">{{ option.description }}</label>
                </div>

                <template v-else-if="option.type==='text' || option.type==='email'">
                    <label class="form-label" :for="'er-' + key">{{ label(option.description) }}</label>
                    <input :type="option.type" :id="'er-' + key" class="form-control input-285" v-model="config[key]" />
                </template>

                <template v-else-if="option.type==='feedselect'">
                    <label class="form-label" :for="'er-' + key">{{ label(option.description) }}</label>
                    <select :id="'er-' + key" class="form-select input-285" v-model="config[key]">
                        <option v-for="feed in feedList" :key="feed.id" :value="String(feed.id)">{{ feed.name }}</option>
                    </select>
                    <div class="form-text">Selects a feed named {{ option.autoname }} when not set.</div>
                </template>
            </div>

            <div class="panel-buttons">
                <button class="btn btn-primary" @click="save">Save</button>
                <button class="btn btn-default" @click="sendtest">Send test email</button>
            </div>
            <div v-if="message" class="alert er-message" :class="messageOk ? 'alert-success' : 'alert-warning'">{{ message }}</div>
        </div>
    </div>

    <div class="panel" v-show="previewHtml">
        <div class="panel-header panel-header-static">
            <span class="panel-accent"></span>
            <span class="panel-name">Email preview</span>
        </div>
        <div class="er-preview" v-html="previewHtml"></div>
    </div>
</div>

<script>

Vue.createApp({
    data() {
        return {
            emailreports: <?php echo json_encode($emailreports); ?>,
            report: "",
            config: {},
            feedList: [],
            feedsByName: {},
            message: "",
            messageOk: false,
            previewHtml: "",
            previewTimer: null,
            suspendAutoPreview: false,
            lastPreviewComparable: ""
        };
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
        setReport: function (report) {
            if (report === this.report) return;
            this.report = report;
            this.previewHtml = "";
            this.loadConfigView();
        },
        label: function (description) {
            return description.replace(/:\s*$/, "");
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
        postText: function (url, params) {
            var body = new URLSearchParams(params);
            return fetch(url, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString() }).then(function (response) {
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
            var url = path + "emailreport/save";
            this.postText(url, { report: this.report, config: JSON.stringify(this.config) }).then(function (raw) {
                var parsed = null;
                try {
                    parsed = JSON.parse(raw);
                } catch (e) {
                    parsed = raw;
                }

                self.messageOk = !!(parsed && parsed.success);
                if (self.messageOk) {
                    self.message = "Config saved";
                } else {
                    self.message = typeof parsed === "string" ? parsed : JSON.stringify(parsed);
                }
            });
        },
        preview: function () {
            var self = this;
            var url = path + "emailreport/preview";
            this.postText(url, { report: this.report, config: JSON.stringify(this.config) }).then(function (result) {
                self.previewHtml = result;
                self.$nextTick(function () {
                    var emailouter = document.getElementById("emailouter");
                    if (emailouter) emailouter.style.padding = "0px";
                });
            });
        },
        sendtest: function () {
            var self = this;
            var url = path + "emailreport/preview/sendtest";
            this.message = "Sending...";
            this.messageOk = false;
            this.postText(url, { report: this.report, config: JSON.stringify(this.config) }).then(function (result) {
                self.messageOk = result === "email report sent";
                self.message = result;
            });
        },
        userGet: function () {
            var self = this;
            return this.fetchJSON(path + "user/get.json").then(function (result) {
                if (self.configOptions.email && result && result.email !== undefined) {
                    self.config.email = result.email;
                }
            });
        }
    }
}).mount("#emailreport-app");

</script>

