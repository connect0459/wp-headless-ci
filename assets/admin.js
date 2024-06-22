"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const autoUpdateCheckbox = document.getElementById("auto_update");
  const manualTriggerButton = document.getElementById("manual_trigger");
  const ciProviderSelect = document.getElementById("ci_provider");
  const tokenLabel = document.querySelector('label[for="token"]');
  const repoUrlLabel = document.querySelector('label[for="repo_url"]');

  function toggleManualTrigger() {
    if (autoUpdateCheckbox.checked) {
      manualTriggerButton.style.display = "none";
    } else {
      manualTriggerButton.style.display = "inline-block";
    }
  }

  function updateLabels() {
    const provider = ciProviderSelect.value;
    if (provider === "github") {
      tokenLabel.textContent = "GitHub Token";
      repoUrlLabel.textContent = "GitHub Repository URL";
    } else if (provider === "gitlab") {
      tokenLabel.textContent = "GitLab Token";
      repoUrlLabel.textContent = "GitLab Repository URL";
    }
  }

  function triggerCI(e) {
    e.preventDefault();
    fetch(ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=trigger_ci",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.text();
      })
      .then(() => {
        alert("CI/CDワークフローが実行されました。");
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("エラーが発生しました。もう一度お試しください。");
      });
  }

  // イベントリスナーの設定
  autoUpdateCheckbox.addEventListener("change", toggleManualTrigger);
  ciProviderSelect.addEventListener("change", updateLabels);

  // 手動トリガーボタンを設定ページに追加
  const submitButton = document.querySelector(".submit");
  if (submitButton && !manualTriggerButton) {
    manualTriggerButton = document.createElement("button");
    manualTriggerButton.id = "manual_trigger";
    manualTriggerButton.className = "button button-secondary";
    manualTriggerButton.textContent = "CI/CDを実行";
    submitButton.parentNode.insertBefore(manualTriggerButton, submitButton);
    manualTriggerButton.addEventListener("click", triggerCI);
  }

  // 初期状態の設定
  toggleManualTrigger();
  updateLabels();
});
