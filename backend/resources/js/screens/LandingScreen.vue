<template>
  <main class="landing-screen">
    <header class="landing-nav">
      <BrandMark />
      <div class="landing-nav__actions">
        <span class="landing-status"><span />Online workspace</span>
        <QdsButton icon="arrowLeft" class="landing-sign-in" @click="$emit('sign-in')">Sign in</QdsButton>
      </div>
    </header>

    <section class="landing-hero">
      <div class="landing-copy">
        <p class="landing-kicker">FIELDWORK / REVIEW / INSIGHT</p>
        <h1>Better data.<br><span>Stronger healthcare.</span></h1>
        <p class="landing-copy__intro">
          DataKit brings facility assessments, offline fieldwork, evidence, and review teams into one clear workspace.
        </p>
        <div class="landing-copy__actions">
          <QdsButton icon="arrowLeft" @click="$emit('sign-in')">Open workspace</QdsButton>
          <a class="landing-download" href="/downloads/datakit-qa.apk" download>
            <AppIcon name="upload" />
            Download Android app
          </a>
        </div>
        <p class="landing-copy__hint">Secure access for assigned teams</p>
        <div class="landing-proof">
          <div>
            <strong>Offline first</strong>
            <span>Keep working between connections.</span>
          </div>
          <div>
            <strong>One source of truth</strong>
            <span>Move clean data into review.</span>
          </div>
        </div>
      </div>

      <div class="landing-stage" aria-label="DataKit fieldwork and review workspace preview">
        <div class="landing-desktop-preview">
          <div class="preview-window-bar"><span /><span /><span /></div>
          <div class="preview-desktop-body">
            <aside>
              <BrandMark theme="light" />
              <span class="preview-nav preview-nav--active">Dashboard</span>
              <span class="preview-nav">My assessments</span>
              <span class="preview-nav">Submissions</span>
              <span class="preview-nav">Reports</span>
            </aside>
            <div class="preview-dashboard">
              <p class="preview-eyebrow">WORKSPACE OVERVIEW</p>
              <h2>Dashboard</h2>
              <div class="preview-metrics">
                <div><span>Completion</span><strong>57%</strong></div>
                <div><span>In review</span><strong>2</strong></div>
                <div><span>Submitted</span><strong>9</strong></div>
              </div>
              <div class="preview-panel">
                <div class="preview-panel__heading"><strong>Facility completion</strong><span>Live</span></div>
                <div class="preview-bars"><i /><i /><i /><i /><i /><i /></div>
                <div class="preview-rows"><span /><span /><span /><span /></div>
              </div>
            </div>
          </div>
        </div>

        <div class="landing-phone-preview">
          <div class="phone-speaker" />
          <div class="phone-topline">
            <BrandMark />
            <span class="phone-online"><i /> Online</span>
          </div>
          <p class="phone-date">FRI 09 OCT 2026 - EXERCISE 2026-Q3-QA</p>
          <h2>Afternoon,<br>Amina.</h2>
          <div class="phone-draft">
            <span>DRAFT IN PROGRESS</span>
            <strong>University of Abuja<br>Teaching Hospital</strong>
            <div />
            <b>Continue draft <em>-></em></b>
          </div>
          <div class="phone-counts"><span><b>1</b><small>DRAFTS</small></span><span><b>0</b><small>PENDING</small></span><span><b>0</b><small>SYNCED</small></span></div>
          <div class="phone-start">Start assessment <b>+</b></div>
        </div>
      </div>
    </section>

    <section class="landing-strip">
      <p>Designed for the whole assessment cycle</p>
      <div><strong>01</strong><span>Assign facilities</span></div>
      <div><strong>02</strong><span>Collect evidence</span></div>
      <div><strong>03</strong><span>Review with confidence</span></div>
    </section>

    <section class="landing-section landing-why">
      <div class="landing-section__inner">
        <div class="landing-section__intro">
          <p class="landing-kicker">WHY DATAKIT</p>
          <h2>Make every visit count.</h2>
          <p>DataKit gives field teams a calmer way to collect reliable information, even when the network is not on their side.</p>
        </div>
        <div class="landing-benefits">
          <article>
            <strong>01</strong>
            <h3>Work without waiting</h3>
            <p>Assigned facilities and published forms stay available offline, so a weak connection never becomes a lost visit.</p>
          </article>
          <article>
            <strong>02</strong>
            <h3>Capture the full picture</h3>
            <p>Structured answers, location, and evidence travel together in one assessment record.</p>
          </article>
          <article>
            <strong>03</strong>
            <h3>Review what matters</h3>
            <p>Scores, validation issues, and flagged answers help reviewers move quickly from submission to action.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="landing-workflow">
      <div class="landing-section__inner">
        <div class="landing-workflow__heading">
          <p class="landing-kicker">ONE CONNECTED WORKFLOW</p>
          <h2>From assignment to action.</h2>
          <p>Keep the same context from the first facility assignment to the final review decision.</p>
        </div>
        <div class="landing-workflow__steps">
          <div class="workflow-step">
            <div class="workflow-step__number">01</div>
            <div><h3>Prepare</h3><p>Assign exercises, teams, and facilities from one workspace.</p></div>
          </div>
          <div class="workflow-step">
            <div class="workflow-step__number">02</div>
            <div><h3>Assess</h3><p>Complete the checklist on the web or in the mobile field app.</p></div>
          </div>
          <div class="workflow-step">
            <div class="workflow-step__number">03</div>
            <div><h3>Improve</h3><p>Review evidence, scores, and issues to guide the next decision.</p></div>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-section landing-clarity">
      <div class="landing-section__inner landing-clarity__inner">
        <div class="landing-clarity__visual">
          <div class="clarity-window">
            <div class="clarity-window__head"><span>SUBMISSION REVIEW</span><b>READY FOR REVIEW</b></div>
            <div class="clarity-score"><strong>82.5</strong><span>/ 100</span><small>Assessment score</small></div>
            <div class="clarity-row"><span>Facility evidence</span><b>Complete</b></div>
            <div class="clarity-row"><span>Validation issues</span><b>2 to resolve</b></div>
            <div class="clarity-row"><span>Review status</span><b>Under review</b></div>
          </div>
        </div>
        <div class="landing-section__intro">
          <p class="landing-kicker">BUILT FOR CLARITY</p>
          <h2>Less chasing. More knowing.</h2>
          <p>Every answer has a place, every submission has a trail, and every reviewer can see what needs attention next.</p>
          <div class="landing-quote">“The best workflow is the one that lets the team focus on the facility, not the paperwork.”</div>
        </div>
      </div>
    </section>

    <section class="landing-cta">
      <div class="landing-cta__inner">
        <div>
          <p class="landing-kicker">READY WHEN YOU ARE</p>
          <h2>Bring better data to the next round.</h2>
        </div>
        <div class="landing-cta__actions">
          <QdsButton icon="arrowLeft" @click="$emit('sign-in')">Sign in to DataKit</QdsButton>
          <a class="landing-download landing-download--light" href="/downloads/datakit-qa.apk" download>
            <AppIcon name="upload" />
            Get the Android app
          </a>
        </div>
      </div>
    </section>

    <footer class="landing-footer">
      <div class="landing-footer__inner">
        <BrandMark theme="light" />
        <span>Quality assurance, from the field to the review room.</span>
        <a href="/downloads/datakit-qa.apk" download>Download Android app</a>
      </div>
    </footer>
  </main>
</template>

<script setup lang="ts">
import BrandMark from '../components/layout/BrandMark.vue';
import AppIcon from '../AppIcon.vue';
import QdsButton from '../components/qds/QdsButton.vue';

defineEmits<{
  'sign-in': [];
}>();
</script>

<style scoped>
.landing-screen {
  min-height: 100vh;
  overflow: hidden;
  color: #10251d;
  background: #f5f5ef;
}

.landing-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: min(1360px, calc(100% - 128px));
  min-height: 82px;
  margin: 0 auto;
}

.landing-nav__actions,
.landing-copy__actions,
.landing-status,
.phone-topline,
.preview-panel__heading {
  display: flex;
  align-items: center;
}

.landing-nav__actions {
  gap: 18px;
}

.landing-status {
  gap: 7px;
  color: #346253;
  font-size: 12px;
  font-weight: 800;
}

.landing-status span,
.phone-online i {
  display: block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #1ca66a;
}

.landing-sign-in :deep(.app-icon),
.landing-copy__actions :deep(.app-icon) {
  transform: rotate(180deg);
}

.landing-hero {
  display: grid;
  grid-template-columns: minmax(330px, 0.8fr) minmax(600px, 1.2fr);
  align-items: center;
  gap: 38px;
  width: min(1360px, calc(100% - 128px));
  min-height: calc(100vh - 82px);
  margin: 0 auto;
  padding: 42px 0 78px;
}

.landing-copy {
  position: relative;
  z-index: 2;
  padding-bottom: 26px;
}

.landing-kicker,
.preview-eyebrow,
.phone-date {
  margin: 0 0 16px;
  color: #087a4a;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0;
}

.landing-copy h1 {
  max-width: 560px;
  margin: 0;
  color: #10251d;
  font-size: 72px;
  line-height: 0.96;
  letter-spacing: 0;
}

.landing-copy h1 span {
  color: #087a4a;
}

.landing-copy__intro {
  max-width: 490px;
  margin: 26px 0 0;
  color: #617169;
  font-size: 17px;
  line-height: 1.55;
}

.landing-copy__actions {
  gap: 16px;
  margin-top: 30px;
}

.landing-copy__hint {
  margin: 10px 0 0;
  color: #617169;
  font-size: 11px;
  font-weight: 800;
}

.landing-download {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 15px;
  border: 1px solid #a9c9b8;
  border-radius: 7px;
  color: #0e5a3a;
  background: #fff;
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
  white-space: nowrap;
}

.landing-download:hover {
  border-color: #0e5a3a;
  background: #edf7f0;
}

.landing-download :deep(.app-icon) {
  width: 15px;
  height: 15px;
  transform: rotate(180deg);
}

.landing-proof {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  max-width: 490px;
  gap: 24px;
  margin-top: 60px;
  padding-top: 18px;
  border-top: 1px solid #d8ded9;
}

.landing-proof div {
  display: grid;
  gap: 5px;
}

.landing-proof strong {
  font-size: 13px;
}

.landing-proof span {
  color: #718078;
  font-size: 11px;
  line-height: 1.4;
}

.landing-stage {
  position: relative;
  min-height: 640px;
}

.landing-stage::before {
  position: absolute;
  top: 3%;
  right: 3%;
  width: 76%;
  height: 86%;
  border: 1px solid #c7d7ce;
  border-radius: 34px;
  background: #dceee3;
  content: '';
  transform: rotate(5deg);
}

.landing-desktop-preview {
  position: absolute;
  top: 7%;
  right: 0;
  width: 77%;
  overflow: hidden;
  border: 1px solid #cbd4cd;
  border-radius: 8px;
  background: #f6f6f1;
  box-shadow: 0 24px 50px rgba(16, 37, 29, 0.12);
  transform: rotate(3deg);
}

.preview-window-bar {
  display: flex;
  gap: 7px;
  height: 26px;
  padding: 9px 12px;
  border-bottom: 1px solid #d8ddd7;
  background: #eff1eb;
}

.preview-window-bar span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #b8c0b9;
}

.preview-desktop-body {
  display: grid;
  grid-template-columns: 27% 1fr;
  min-height: 390px;
}

.preview-desktop-body aside {
  display: grid;
  align-content: start;
  gap: 12px;
  padding: 20px 13px;
  background: #063f2c;
}

.preview-desktop-body aside :deep(.brand-logo) {
  width: 102px;
  height: 25px;
  margin-bottom: 20px;
}

.preview-nav {
  display: block;
  padding: 10px 9px;
  border-radius: 5px;
  color: #a3c9b7;
  font-size: 10px;
  font-weight: 800;
}

.preview-nav--active {
  color: #073c2b;
  background: #c7ecd6;
}

.preview-dashboard {
  padding: 28px 24px;
}

.preview-eyebrow {
  margin-bottom: 7px;
  font-size: 8px;
}

.preview-dashboard h2 {
  margin: 0 0 20px;
  font-size: 30px;
}

.preview-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
}

.preview-metrics div,
.preview-panel {
  border: 1px solid #d9e0d9;
  border-radius: 7px;
  background: #fff;
}

.preview-metrics div {
  display: grid;
  gap: 6px;
  min-height: 72px;
  padding: 11px;
}

.preview-metrics span,
.preview-panel__heading span {
  color: #64736b;
  font-size: 9px;
  font-weight: 800;
}

.preview-metrics strong {
  font-size: 25px;
}

.preview-panel {
  margin-top: 13px;
  padding: 15px;
}

.preview-panel__heading {
  justify-content: space-between;
  margin-bottom: 18px;
  font-size: 13px;
}

.preview-bars,
.preview-rows {
  display: flex;
  gap: 7px;
}

.preview-bars {
  align-items: end;
  height: 98px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e3e8e2;
}

.preview-bars i {
  flex: 1;
  border-radius: 4px 4px 0 0;
  background: #0e5a3a;
}

.preview-bars i:nth-child(1) { height: 42%; }
.preview-bars i:nth-child(2) { height: 65%; }
.preview-bars i:nth-child(3) { height: 54%; }
.preview-bars i:nth-child(4) { height: 83%; }
.preview-bars i:nth-child(5) { height: 70%; background: #a9d9bb; }
.preview-bars i:nth-child(6) { height: 94%; background: #a9d9bb; }

.preview-rows {
  display: grid;
  gap: 9px;
  margin-top: 14px;
}

.preview-rows span {
  display: block;
  width: 74%;
  height: 7px;
  border-radius: 4px;
  background: #e0e7e0;
}

.preview-rows span:nth-child(even) {
  width: 48%;
}

.landing-phone-preview {
  position: absolute;
  bottom: 0;
  left: 4%;
  z-index: 2;
  width: min(330px, 45%);
  min-height: 555px;
  padding: 50px 24px 28px;
  overflow: hidden;
  border: 10px solid #122c22;
  border-radius: 38px;
  color: #10251d;
  background: #f5f5ef;
  box-shadow: 0 24px 50px rgba(16, 37, 29, 0.18);
}

.phone-speaker {
  position: absolute;
  top: 14px;
  left: 50%;
  width: 102px;
  height: 20px;
  border-radius: 12px;
  background: #122c22;
  transform: translateX(-50%);
}

.phone-topline {
  justify-content: space-between;
  gap: 8px;
}

.phone-topline :deep(.brand-logo) {
  width: 116px;
  height: 28px;
}

.phone-online {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 9px;
  border-radius: 16px;
  color: #0e5a3a;
  background: #dff0e5;
  font-size: 10px;
  font-weight: 900;
}

.phone-date {
  margin: 25px 0 8px;
  color: #65756b;
  font-size: 8px;
  letter-spacing: 0;
}

.landing-phone-preview h2 {
  margin: 0 0 18px;
  font-size: 35px;
  line-height: 0.95;
}

.phone-draft {
  display: grid;
  gap: 10px;
  padding: 19px;
  border-radius: 19px;
  color: #fff;
  background: #063f2c;
}

.phone-draft > span {
  color: #a9d9bb;
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0;
}

.phone-draft > strong {
  font-size: 17px;
  line-height: 1.1;
}

.phone-draft > div {
  height: 1px;
  border-top: 1px dashed #4d8d72;
}

.phone-draft > b {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
}

.phone-draft em {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  color: #063f2c;
  background: #dff0e5;
  font-size: 17px;
  font-style: normal;
}

.phone-counts {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  margin-top: 18px;
  border: 1px solid #d9e0d9;
  border-radius: 14px;
  background: #fff;
}

.phone-counts span {
  display: grid;
  gap: 4px;
  padding: 12px 10px;
  border-right: 1px solid #d9e0d9;
}

.phone-counts span:last-child { border-right: 0; }
.phone-counts b { font-size: 23px; }
.phone-counts small { color: #65756b; font-size: 8px; font-weight: 900; }

.phone-start {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 20px;
  padding: 16px 18px;
  border-radius: 14px;
  color: #fff;
  background: #087a4a;
  font-size: 14px;
  font-weight: 900;
}

.phone-start b { font-size: 22px; font-weight: 400; }

.landing-strip {
  display: grid;
  grid-template-columns: 1.2fr repeat(3, 1fr);
  align-items: center;
  gap: 24px;
  width: min(1360px, calc(100% - 128px));
  margin: 0 auto;
  padding: 18px 0 26px;
  border-top: 1px solid #d8ded9;
}

.landing-strip > p {
  margin: 0;
  color: #087a4a;
  font-size: 12px;
  font-weight: 900;
}

.landing-strip > div {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #617169;
  font-size: 11px;
  font-weight: 800;
}

.landing-strip strong { color: #087a4a; }

.landing-section {
  padding: 118px 0;
}

.landing-section__inner,
.landing-cta__inner,
.landing-footer__inner {
  width: min(1360px, calc(100% - 128px));
  margin: 0 auto;
}

.landing-section__inner {
  display: grid;
  grid-template-columns: minmax(250px, 0.75fr) minmax(0, 1.25fr);
  gap: 88px;
  align-items: start;
}

.landing-section__intro {
  max-width: 480px;
}

.landing-section__intro h2,
.landing-workflow h2,
.landing-cta h2 {
  margin: 0;
  color: #10251d;
  font-size: 48px;
  line-height: 1.02;
}

.landing-section__intro > p:not(.landing-kicker),
.landing-workflow__heading > p:not(.landing-kicker) {
  margin: 21px 0 0;
  color: #65756b;
  font-size: 16px;
  line-height: 1.6;
}

.landing-benefits {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
}

.landing-benefits article {
  min-height: 245px;
  padding: 20px 0 0;
  border-top: 2px solid #0e5a3a;
}

.landing-benefits article > strong,
.workflow-step__number {
  color: #087a4a;
  font-size: 12px;
  font-weight: 900;
}

.landing-benefits h3,
.workflow-step h3 {
  margin: 38px 0 12px;
  color: #10251d;
  font-size: 19px;
}

.landing-benefits p,
.workflow-step p {
  margin: 0;
  color: #65756b;
  font-size: 13px;
  line-height: 1.6;
}

.landing-workflow {
  padding: 104px 0;
  color: #fff;
  background: #063f2c;
}

.landing-workflow .landing-section__inner {
  grid-template-columns: minmax(250px, 0.7fr) minmax(0, 1.3fr);
}

.landing-workflow .landing-kicker {
  color: #9be0b7;
}

.landing-workflow h2 {
  color: #fff;
}

.landing-workflow__heading > p:not(.landing-kicker) {
  color: #b4d2c2;
}

.landing-workflow__steps {
  display: grid;
  gap: 0;
  border-top: 1px solid #3d765e;
}

.workflow-step {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr);
  gap: 18px;
  padding: 25px 0;
  border-bottom: 1px solid #3d765e;
}

.workflow-step h3 {
  margin: 0 0 7px;
  color: #fff;
}

.workflow-step p {
  color: #b4d2c2;
}

.workflow-step__number {
  color: #9be0b7;
}

.landing-clarity {
  background: #e7f1ea;
}

.landing-clarity__inner {
  align-items: center;
}

.landing-clarity__visual {
  min-height: 330px;
  padding: 36px;
  border-radius: 8px;
  background: #cfe8d9;
}

.clarity-window {
  max-width: 490px;
  margin: 0 auto;
  padding: 22px;
  border: 1px solid #d9e0d9;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 18px 32px rgba(16, 37, 29, 0.1);
}

.clarity-window__head,
.clarity-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.clarity-window__head {
  padding-bottom: 16px;
  border-bottom: 1px solid #e0e7e0;
  color: #087a4a;
  font-size: 10px;
  font-weight: 900;
}

.clarity-window__head b {
  padding: 6px 8px;
  border-radius: 12px;
  color: #087a4a;
  background: #dff0e5;
  font-size: 9px;
}

.clarity-score {
  display: grid;
  grid-template-columns: auto auto;
  align-items: end;
  gap: 4px;
  padding: 26px 0 20px;
}

.clarity-score strong {
  color: #10251d;
  font-size: 56px;
  line-height: 0.9;
}

.clarity-score span {
  color: #65756b;
  font-size: 14px;
}

.clarity-score small {
  grid-column: 1 / -1;
  color: #65756b;
  font-size: 11px;
}

.clarity-row {
  padding: 13px 0;
  border-top: 1px solid #e0e7e0;
  color: #65756b;
  font-size: 12px;
}

.clarity-row b {
  color: #0e5a3a;
  font-size: 11px;
}

.landing-quote {
  margin-top: 32px;
  padding-left: 18px;
  border-left: 2px solid #0e5a3a;
  color: #346253;
  font-size: 15px;
  font-weight: 800;
  line-height: 1.55;
}

.landing-cta {
  padding: 68px 0;
  background: #f5f5ef;
}

.landing-cta__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 36px;
  padding: 42px 48px;
  border-radius: 8px;
  background: #dff0e5;
}

.landing-cta h2 {
  max-width: 620px;
  font-size: 38px;
}

.landing-cta__actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.landing-download--light {
  border-color: #8bbfa5;
  color: #0e5a3a;
  background: transparent;
}

.landing-cta .landing-kicker {
  margin-bottom: 13px;
}

.landing-footer {
  padding: 34px 0;
  color: #b4d2c2;
  background: #063f2c;
}

.landing-footer__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.landing-footer__inner :deep(.brand-logo) {
  width: 128px;
  height: 31px;
}

.landing-footer__inner span,
.landing-footer__inner a {
  font-size: 11px;
  font-weight: 800;
}

.landing-footer__inner a {
  color: #9be0b7;
  text-decoration: none;
}

.landing-footer__inner a:hover {
  color: #fff;
}

@media (max-width: 1050px) {
  .landing-hero {
    grid-template-columns: 1fr;
    gap: 8px;
    padding-top: 26px;
  }

  .landing-copy { padding-bottom: 0; }
  .landing-copy h1 { font-size: 56px; }
  .landing-stage { min-height: 570px; }
  .landing-strip { grid-template-columns: repeat(3, 1fr); }
  .landing-strip > p { grid-column: 1 / -1; }
  .landing-section__inner,
  .landing-workflow .landing-section__inner { grid-template-columns: 1fr; gap: 44px; }
}

@media (max-width: 640px) {
  .landing-nav,
  .landing-hero,
  .landing-strip,
  .landing-section__inner,
  .landing-cta__inner,
  .landing-footer__inner { width: min(100% - 32px, 520px); }
  .landing-nav { min-height: 70px; }
  .landing-status { display: none; }
  .landing-hero { min-height: auto; padding: 34px 0 28px; }
  .landing-copy h1 { font-size: 48px; }
  .landing-copy__intro { font-size: 15px; }
  .landing-copy__actions { align-items: stretch; flex-direction: column; }
  .landing-download { width: fit-content; }
  .landing-proof { margin-top: 38px; }
  .landing-stage { min-height: 510px; margin: 0 -8px; }
  .landing-desktop-preview { top: 6%; right: -23%; width: 88%; }
  .landing-phone-preview { left: 0; width: 68%; min-height: 450px; padding: 42px 16px 20px; border-width: 7px; border-radius: 28px; }
  .phone-topline :deep(.brand-logo) { width: 102px; }
  .landing-phone-preview h2 { font-size: 29px; }
  .phone-draft { padding: 14px; }
  .phone-draft > strong { font-size: 14px; }
  .phone-counts { margin-top: 12px; }
  .phone-counts span { padding: 9px 7px; }
  .phone-counts b { font-size: 19px; }
  .phone-start { margin-top: 13px; padding: 13px; font-size: 11px; }
  .landing-strip { grid-template-columns: 1fr; gap: 10px; }
  .landing-strip > p { margin-bottom: 4px; }
  .landing-section { padding: 78px 0; }
  .landing-section__intro h2,
  .landing-workflow h2 { font-size: 38px; }
  .landing-benefits { grid-template-columns: 1fr; gap: 0; }
  .landing-benefits article { min-height: auto; padding: 18px 0 24px; }
  .landing-benefits h3 { margin: 22px 0 8px; }
  .landing-workflow { padding: 78px 0; }
  .landing-clarity__visual { min-height: auto; padding: 16px; }
  .landing-cta { padding: 40px 0; }
  .landing-cta__inner { align-items: flex-start; flex-direction: column; padding: 28px 22px; }
  .landing-cta h2 { font-size: 32px; }
  .landing-cta__actions { justify-content: flex-start; }
  .landing-footer__inner { align-items: flex-start; flex-direction: column; }
}
</style>
