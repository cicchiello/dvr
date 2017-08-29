<!DOCTYPE html>
<html>
  <head>
    
<link href="https://www.w3schools.com/w3css/4/w3.css" media="all" rel="stylesheet">
<link href="./style.css" media="all" rel="stylesheet">

<script type="text/javascript">
'use strict';
var DeviceTemplate = {};
var devices = {};
var page = {};

function edge_detect()
{
	return (navigator.appVersion.indexOf(' Edge/') != -1)
}

function fetch_json(url, cb)
{
    if (!url) return;

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.open('GET', url, true);
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState != 4) return;

	if (xmlhttp.status != 200) {
	    if (!xmlhttp.status && edge_detect()) {
                discover_none.textContent =
                    'Network access prohibited: Your browser (Microsoft Edge) is preventing the detection of HDHomeRun devices.';
	    }
	    console.error('server returned http ' + xmlhttp.status.toString() + ' for ' + url);
	    return;
	}

	var json = JSON.parse(xmlhttp.responseText);
	json && cb(json);
    }
    xmlhttp.send();
}

function update_vars(device) {
    //device['BaseURL'] = device['BaseURL'] || 'http://' + devices['LocalIP'];
    device['ScanURL'] = device['BaseURL'] + '/lineup.html';
    device['Version'] = device['Version'] || device['FirmwareVersion'];
    device['RecordingsURL'] = './recordings.php';
    device['SchedulesURL'] = device['BaseURL'] + '/lineup.html';

    if (device['ChannelCount'] > 0) {
        getSubElement.call(device['element'], 'ChStatus').classList.remove('warn');
    } else {
        device['ChannelCount'] = 'No';
    }

    getSubElement.call(device['element'], 'FwStatus').classList.remove('warn');

    if (device['ConditionalAccess'] == 1) {
        getSubElement.call(device['element'], 'CC').classList.remove('w3-hide');
	device['CCURL'] = 'http://' + device['LocalIP'] + '/cc.html';
    }
    if (device['TuningResolver'] == 1) {
        getSubElement.call(device['element'], 'TR').classList.remove('w3-hide');
	device['TRURL'] = 'http://' + device['LocalIP'] + '/tr.html';
    }

    //console.log("Vars updated");
}

function getSubElement(name)
{
	if (this.classList.contains('_' + name)) {
		return this;
	}

	return this.getElementsByClassName('_' + name)[0];
}

function renderTemplate(element, object)
{
    for (var key in object) {
	if (!element || !object || !key) continue;

	var subElement = getSubElement.call(element, key);
	var value = object[key];

	if (value === undefined || !subElement) continue;

console.log("key: "+key);
console.log("value: "+value);
	switch(subElement.tagName.toLowerCase()) {
	case 'a':
	    subElement.href = value;
	    break;
	default:
	    if (subElement.childNodes[0]) {
		subElement.childNodes[0].data = value;
	    } else {
		subElement.textContent = value;
	    }
	}
    }
}


function sorted_element(device) {
    var element = document.importNode(DeviceTemplate, true);
    var inserted = false;
    for (var ip in devices) {
	var other = devices[ip];
	if (!other['element']) continue;

	if (device['DeviceID'] < other['DeviceID']) {
	    discover_results.insertBefore(element, other['element']);
	    discover_results.insertBefore(document.createTextNode(" "), other['element']);
	    inserted = true;
	    break;
	}
    }

    if (!inserted) {
	discover_results.appendChild(element);
	discover_results.appendChild(document.createTextNode(" "));
    }

    return element;
}

function render_device(device) {
    device['element'] = device['element'] || sorted_element(device);
    device['element'].id = '';

    update_vars(device);

    renderTemplate(device['element'], device);
    discover_none.classList.add('w3-hide');
}

function render_all() {
    for (var n in devices) {
        var device = devices[n];
	//console.log("device: "+JSON.stringify(device,null,3));
	device['element'] && update_vars(device);
    }
    renderTemplate(document.body, page);
    //console.log("render_all done");
}

function discover_device(device)
{
    var parser = function parse_discover_device(json) {
        for (var key in json) {
	    device[key] = json[key];
	}
	device_lineup(device);

	if (device['LineupURL'] === null) legacy_device.classList.remove('w3-hide');

	render_device(device);
	render_all();
	//console.log("device: "+JSON.stringify(device,null,3));
    };
				 
    fetch_json(device['DiscoverURL'], parser);
}

function device_lineup(device)
{
    var parser = function parse_device_lineup(json) {
	device['Lineup'] = json;
	device['ChannelCount'] = json.length;
	render_device(device);
	render_all();
    };
    fetch_json(device['LineupURL'], parser);
}

function discover()
{
    var parser = function parse_discover(doc) {
        devices = {};
	//discover_results.innerHTML = '';
	discover_none.classList.remove('w3-hide');
	for (var n in doc) {
	    var item = doc[n];
	    //console.log(JSON.stringify(item,null,3));
	    var device = devices[item['LocalIP']] || item;
	    devices[item['LocalIP']] = device;			 
	    discover_device(device);
	}
	render_all();
    };
    
    fetch_json('http://ipv4-api.hdhomerun.com/discover', parser);
}

function removeElement(element)
{
	element && element.parentNode && element.parentNode.removeChild(element);
	return element;
}

function init()
{
    //discover_refresh.onclick = discover;
    //console.log("device_template: "+(new XMLSerializer().serializeToString(document.getElementById('device_template'))));
    DeviceTemplate = removeElement(document.getElementById('device_template'));
    //console.log("DeviceTemplate: "+(new XMLSerializer().serializeToString(DeviceTemplate)));
    discover();
}

window.onload = init;
</script>
</head>
<body class="bg">
  
    <div class="row">
      
      <div id="discover_none">
        <div class="w3-panel w3-card w3-white w3-round-large w3-display-bottommiddle">
	  <p>No HDHomeRun detected.</p>
	  <p>Please connect the HDHomeRun to your router and refresh the page.</p>
	  <p>HDHomeRun PRIME: Please remove the CableCard to allow detection to complete.</p>
	</div>
      </div>
	
      <div id="discover_results" class="row">
	<div class="w3-hide">
	  <div id="device_template" class="box col-sm-4">
	    <div class="w3-panel w3-card w3-white w3-round-large w3-display-bottommiddle">
	      <div>
		<span class="_FriendlyName">HDHomeRun</span>
		<span class="_DeviceID"></span>
	      </div>
	      <ul class="checklist">
		<li class="_FwStatus warn">Version
		  <span class="_Version">unknown</span>
		</li>
		<li class="_ChStatus warn">
		  <a class="_ScanURL">
		    <span class="_ChannelCount">0</span>
		    Channels
		  </a>
		  <span class="help"></span>
		</li>
		<li>
		  <a class="_RecordingsURL">
		    <span class="_RecordingsCount">0</span>
		    Recordings
		  </a>
		  <span class="help"></span>
		</li>
		<li>
		  <a class="_SchedulesURL">
		    <span class="_ScheduleCount">0</span>
		    Schedules
		  </a>
		  <span class="help"></span>
		</li>
		<li class="_CC w3-hide">
		  <a class="_CCURL">CableCARD&trade; Menu</a>
		  <span class="help"></span>
		</li>
		<li class="_TR w3-hide">
		  <a class="_TRURL">Tuning Resolver Menu</a>
		</li>
	      </ul>
	    </div>
	  </div>
	</div>
      </div>

    </div>
    </div>
    
</body>
</html>
