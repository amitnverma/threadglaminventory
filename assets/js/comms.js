/**
 * Communications session — in-browser recording (mic + call/tab), transcript, AI.
 * No external desktop apps required.
 */
(function () {
  var card = document.getElementById('recordingCard');
  // Jump links: open the target panel if collapsed
  document.querySelectorAll('.section-jump a').forEach(function (a) {
    a.addEventListener('click', function () {
      var id = (a.getAttribute('href') || '').replace('#', '');
      var el = id ? document.getElementById(id) : null;
      if (el && el.tagName === 'DETAILS') el.open = true;
    });
  });
  if (!card) return;

  var sessionId = card.getAttribute('data-session-id');
  var apiUrl = card.getAttribute('data-api') || 'comm-api.php';

  var micBtn = document.getElementById('btnRecMic');
  var callBtn = document.getElementById('btnRecCall');
  var stopBtn = document.getElementById('btnRecStop');
  var timerEl = document.getElementById('recTimer');
  var statusEl = document.getElementById('recStatus');

  var mediaRecorder = null;
  var activeStreams = [];
  var audioContext = null;
  var chunks = [];
  var startedAt = 0;
  var tick = null;
  var recognition = null;

  function fmt(sec) {
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function setStatus(msg) {
    if (statusEl) statusEl.textContent = msg || '';
  }

  function setRecordingUi(isRecording) {
    if (micBtn) micBtn.disabled = isRecording;
    if (callBtn) callBtn.disabled = isRecording;
    if (stopBtn) stopBtn.disabled = !isRecording;
  }

  function pickMime() {
    if (typeof MediaRecorder === 'undefined') return '';
    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return 'audio/webm;codecs=opus';
    if (MediaRecorder.isTypeSupported('audio/webm')) return 'audio/webm';
    if (MediaRecorder.isTypeSupported('audio/mp4')) return 'audio/mp4';
    return '';
  }

  function stopAllStreams() {
    activeStreams.forEach(function (stream) {
      try {
        stream.getTracks().forEach(function (t) { t.stop(); });
      } catch (e) {}
    });
    activeStreams = [];
    if (audioContext) {
      try { audioContext.close(); } catch (e) {}
      audioContext = null;
    }
  }

  function beginRecorder(stream) {
    chunks = [];
    var mime = pickMime();
    try {
      mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
    } catch (err) {
      setStatus('Could not start recorder in this browser.');
      stopAllStreams();
      setRecordingUi(false);
      return;
    }

    mediaRecorder.ondataavailable = function (e) {
      if (e.data && e.data.size > 0) chunks.push(e.data);
    };
    mediaRecorder.onerror = function () {
      setStatus('Recording error.');
    };
    mediaRecorder.onstop = function () {
      var blobType = (mediaRecorder && mediaRecorder.mimeType) || mime || 'audio/webm';
      var blob = new Blob(chunks, { type: blobType });
      var duration = Math.round((Date.now() - startedAt) / 1000);
      stopAllStreams();
      mediaRecorder = null;
      if (!blob.size) {
        setStatus('No audio captured. Try again and allow mic / tab audio.');
        setRecordingUi(false);
        if (timerEl) timerEl.textContent = '00:00';
        return;
      }
      uploadBlob(blob, duration);
    };

    mediaRecorder.start(1000);
    startedAt = Date.now();
    setRecordingUi(true);
    if (tick) clearInterval(tick);
    tick = setInterval(function () {
      if (timerEl) timerEl.textContent = fmt(Math.round((Date.now() - startedAt) / 1000));
    }, 250);
  }

  function startMicRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Microphone recording is not supported in this browser. Use Chrome or Edge.');
      return;
    }
    setStatus('Requesting microphone…');
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      activeStreams.push(stream);
      setStatus('Recording microphone…');
      beginRecorder(stream);
    }).catch(function () {
      setStatus('Microphone permission denied — allow mic access and try again.');
      setRecordingUi(false);
    });
  }

  function startCallRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
      setStatus('Call/tab recording needs Chrome or Edge.');
      return;
    }
    setStatus('Choose the call tab and enable “Share tab audio”…');

    navigator.mediaDevices.getDisplayMedia({
      video: true,
      audio: true
    }).then(function (displayStream) {
      activeStreams.push(displayStream);

      // When user stops sharing from the browser UI, end our recording too
      displayStream.getTracks().forEach(function (track) {
        track.addEventListener('ended', function () {
          if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch (e) {}
          }
        });
      });

      var audioTracks = displayStream.getAudioTracks();
      if (!audioTracks.length) {
        setStatus('No tab audio — pick a Chrome tab and check “Share tab audio”, or use Record microphone.');
        stopAllStreams();
        setRecordingUi(false);
        return;
      }

      // Mix tab audio + optional mic (your voice) into one stream
      var tabAudio = new MediaStream(audioTracks);
      var recordStream = tabAudio;

      var finish = function (micStream) {
        if (micStream) activeStreams.push(micStream);
        try {
          audioContext = new (window.AudioContext || window.webkitAudioContext)();
          var dest = audioContext.createMediaStreamDestination();
          audioContext.createMediaStreamSource(tabAudio).connect(dest);
          if (micStream) {
            audioContext.createMediaStreamSource(micStream).connect(dest);
          }
          recordStream = dest.stream;
        } catch (e) {
          // Fallback: tab audio only
          recordStream = tabAudio;
        }
        setStatus('Recording call/tab audio…');
        beginRecorder(recordStream);
      };

      if (navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ audio: true }).then(finish).catch(function () {
          finish(null);
        });
      } else {
        finish(null);
      }
    }).catch(function () {
      setStatus('Screen/tab share cancelled or blocked.');
      setRecordingUi(false);
    });
  }

  function stopRecording() {
    if (tick) {
      clearInterval(tick);
      tick = null;
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      setStatus('Uploading…');
      try { mediaRecorder.stop(); } catch (e) {
        setStatus('Could not stop recorder.');
        stopAllStreams();
        setRecordingUi(false);
      }
    } else {
      stopAllStreams();
      setRecordingUi(false);
      setStatus('');
    }
  }

  if (micBtn) {
    micBtn.addEventListener('click', function () {
      startMicRecording();
    });
  }
  if (callBtn) {
    callBtn.addEventListener('click', function () {
      startCallRecording();
    });
  }
  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      stopRecording();
    });
  }

  function uploadBlob(blob, duration) {
    var fd = new FormData();
    fd.append('action', 'upload_recording');
    fd.append('session_id', sessionId);
    fd.append('duration_sec', String(duration || 0));
    var ext = (blob.type || '').indexOf('mp4') !== -1 ? 'mp4' : 'webm';
    fd.append('audio', blob, 'recording.' + ext);
    setStatus('Uploading…');
    fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          setStatus(data.err || 'Upload failed.');
          setRecordingUi(false);
          return;
        }
        setStatus('Saved. Reloading…');
        window.location.reload();
      })
      .catch(function () {
        setStatus('Upload failed (network).');
        setRecordingUi(false);
      });
  }

  document.querySelectorAll('.btn-save-transcript').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.comm-rec-item');
      if (!item) return;
      var rid = item.getAttribute('data-recording-id');
      var ta = item.querySelector('.rec-transcript');
      var fd = new FormData();
      fd.append('action', 'save_transcript');
      fd.append('session_id', sessionId);
      fd.append('recording_id', rid);
      fd.append('transcript_text', ta ? ta.value : '');
      btn.disabled = true;
      fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.disabled = false;
          btn.textContent = data.ok ? 'Saved' : (data.err || 'Error');
          setTimeout(function () { btn.textContent = 'Save transcript'; }, 1500);
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = 'Error';
        });
    });
  });

  document.querySelectorAll('.btn-dictate').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SpeechRec) {
        alert('Live dictate needs Chrome/Edge. You can still paste a transcript.');
        return;
      }
      var item = btn.closest('.comm-rec-item');
      var ta = item && item.querySelector('.rec-transcript');
      if (!ta) return;

      if (recognition) {
        recognition.stop();
        recognition = null;
        btn.textContent = 'Live dictate (browser)';
        return;
      }

      recognition = new SpeechRec();
      recognition.continuous = true;
      recognition.interimResults = true;
      recognition.lang = 'en-US';
      btn.textContent = 'Stop dictate';
      recognition.onresult = function (ev) {
        var finalText = '';
        for (var i = ev.resultIndex; i < ev.results.length; i++) {
          if (ev.results[i].isFinal) finalText += ev.results[i][0].transcript + ' ';
        }
        if (finalText) {
          ta.value = (ta.value ? ta.value + ' ' : '') + finalText.trim();
        }
      };
      recognition.onerror = function () {
        btn.textContent = 'Live dictate (browser)';
        recognition = null;
      };
      recognition.onend = function () {
        btn.textContent = 'Live dictate (browser)';
        recognition = null;
      };
      recognition.start();
    });
  });

  var suggestBtn = document.getElementById('btnSuggest');
  var suggestBox = document.getElementById('suggestBox');
  if (suggestBtn) {
    suggestBtn.addEventListener('click', function () {
      suggestBtn.disabled = true;
      var fd = new FormData();
      fd.append('action', 'suggest_questions');
      fd.append('session_id', sessionId);
      fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          suggestBtn.disabled = false;
          if (!data.ok) {
            alert(data.err || 'AI suggestion failed.');
            return;
          }
          if (!suggestBox) return;
          suggestBox.hidden = false;
          suggestBox.innerHTML = '<strong>Suggested follow-ups</strong> (click to add):';
          (data.questions || []).forEach(function (q) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-secondary';
            b.style.margin = '0.35rem 0.35rem 0 0';
            b.textContent = q;
            b.addEventListener('click', function () {
              var input = document.getElementById('newQuestionInput');
              if (input) {
                input.value = q;
                input.focus();
              }
              var form = input && input.closest('form');
              if (form) {
                var src = form.querySelector('input[name="source"]');
                if (src) src.value = 'ai';
              }
            });
            suggestBox.appendChild(b);
          });
        })
        .catch(function () {
          suggestBtn.disabled = false;
          alert('AI request failed.');
        });
    });
  }

  var sumBtn = document.getElementById('btnSummarize');
  var aiStatus = document.getElementById('aiStatus');
  if (sumBtn) {
    sumBtn.addEventListener('click', function () {
      sumBtn.disabled = true;
      if (aiStatus) aiStatus.textContent = 'Summarizing (sending text only — not audio)…';
      var fd = new FormData();
      fd.append('action', 'summarize');
      fd.append('session_id', sessionId);
      fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) {
            sumBtn.disabled = false;
            if (aiStatus) aiStatus.textContent = data.err || 'Summarize failed.';
            return;
          }
          if (aiStatus) aiStatus.textContent = 'Done. Reloading…';
          window.location.reload();
        })
        .catch(function () {
          sumBtn.disabled = false;
          if (aiStatus) aiStatus.textContent = 'Network error.';
        });
    });
  }
})();
