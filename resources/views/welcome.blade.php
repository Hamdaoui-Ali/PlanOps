<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PlanOps keeps your projects, tasks, and progress visible.">
        <title>PlanOps — Track the work. See the progress.</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-body">
        <a class="skip-link" href="#main-content">Skip to main content</a>
        <div class="public-shell">
            <header class="public-nav">
                <a href="{{ url('/') }}" class="public-brand" aria-label="PlanOps home"><x-application-logo /></a>
                <nav class="public-nav-links" aria-label="Public navigation"><a href="#principles">Principles</a><a href="#preview">Product</a><a href="#auth">Get Started</a></nav>
                <div class="public-nav-actions"><a href="{{ route('login') }}" class="public-login-link">Log In</a><a href="{{ route('register') }}" class="planops-public-button planops-public-button-primary">Get Started</a></div>
            </header>
            <main id="main-content">
                <section class="public-hero" aria-labelledby="hero-heading">
                    <div class="public-hero-copy">
                        <p class="public-eyebrow"><span class="public-eyebrow-dot"></span> Work operations, made clear</p>
                        <h1 id="hero-heading">Track the work.<br><span>See the progress.</span></h1>
                        <p class="public-hero-description">PlanOps helps you run your work like a system. Projects, tasks, and updates—organized, visible, and always moving forward.</p>
                        <div class="public-hero-actions"><a href="{{ route('register') }}" class="planops-public-button planops-public-button-primary">Get Started <span aria-hidden="true">→</span></a><a href="{{ route('login') }}" class="planops-public-button planops-public-button-outline">Log In</a></div>
                        <div class="public-principle-list" aria-label="PlanOps value propositions"><div><i class="ph ph-target" aria-hidden="true"></i><span><strong>Operational clarity</strong><small>See what’s happening and what’s next.</small></span></div><div><i class="ph ph-pulse" aria-hidden="true"></i><span><strong>Steady progress</strong><small>Make daily progress that compounds.</small></span></div><div><i class="ph ph-shield-check" aria-hidden="true"></i><span><strong>Built for you</strong><small>Secure, private, and personal.</small></span></div></div>
                    </div>
                    <div id="preview" class="public-product-preview" aria-label="Preview of the PlanOps project board">
                        <div class="preview-window-bar"><span class="preview-dot"></span><span>PlanOps</span><span class="preview-project">Acme Website Redesign <i class="ph ph-caret-down" aria-hidden="true"></i></span><span class="preview-search"><i class="ph ph-magnifying-glass" aria-hidden="true"></i> Search tasks…</span><i class="ph ph-bell" aria-hidden="true"></i></div>
                        <div class="preview-body"><aside class="preview-sidebar" aria-label="Preview navigation"><strong><i class="ph ph-kanban" aria-hidden="true"></i> Workspace</strong><span class="preview-nav-active"><i class="ph ph-squares-four" aria-hidden="true"></i> Projects</span><span><i class="ph ph-list-checks" aria-hidden="true"></i> My Work</span><span><i class="ph ph-chart-line-up" aria-hidden="true"></i> Analytics</span><span><i class="ph ph-gear" aria-hidden="true"></i> Settings</span><small>PROJECTS</small><span class="preview-project-active"><i class="ph ph-circle" aria-hidden="true"></i> Acme Website Redesign</span><span><i class="ph ph-circle" aria-hidden="true"></i> Q2 Campaign</span></aside>
                            <div class="preview-content"><div class="preview-heading"><div><small>PROJECT / BOARD</small><h2>Acme Website Redesign</h2></div><button type="button">+ New task</button></div><div class="preview-tabs"><span class="is-active">Board</span><span>List</span><span>Activity</span></div><div class="preview-columns">
                                <div class="preview-column"><h3>BACKLOG <b>4</b></h3><article><strong>Audit current site content</strong><span>Research</span><small>May 19</small></article><article><strong>Define IA and content structure</strong><span>Planning</span><small>May 21</small></article></div>
                                <div class="preview-column"><h3>IN PROGRESS <b>2</b></h3><article><strong>Build homepage</strong><span class="preview-chip-cyan">Development</span><div class="preview-progress"><i style="width: 60%"></i></div><small>60% · May 16</small></article><article><strong>Implement navigation</strong><span class="preview-chip-cyan">Development</span><div class="preview-progress"><i style="width: 75%"></i></div><small>75% · May 15</small></article></div>
                                <div class="preview-column"><h3>IN REVIEW <b>2</b></h3><article><strong>Homepage design</strong><span class="preview-chip-lime">Design</span><div class="preview-progress preview-progress-lime"><i style="width: 90%"></i></div><small>90% · May 14</small></article><article><strong>Content review</strong><span class="preview-chip-lime">Review</span><div class="preview-progress preview-progress-lime"><i style="width: 100%"></i></div><small>100% · May 13</small></article></div>
                                <div class="preview-column preview-column-quiet"><h3>BLOCKED <b>1</b></h3><article><strong>API integration</strong><span class="preview-chip-amber">Blocked</span><small>May 12</small></article><em>+ Add task</em></div>
                                <div class="preview-column"><h3>DONE <b>3</b></h3><article><strong><i class="ph ph-check-square" aria-hidden="true"></i> Project kickoff</strong><span>Management</span><small>Apr 28</small></article><article><strong><i class="ph ph-check-square" aria-hidden="true"></i> Set up project board</strong><span>Management</span><small>Apr 27</small></article></div>
                            </div><div class="preview-footer"><div><small>ACTIVITY</small><div class="preview-sparkline"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div><div><small>PERIOD MOVEMENT</small><strong>23 <small>completed</small></strong></div><div><small>OVERALL PROGRESS</small><strong>68%</strong></div><div><small>RECENT ACTIVITY</small><p>Updated Build homepage <span>15m ago</span></p><p>Added Homepage design <span>1h ago</span></p></div></div></div>
                        </div>
                    </div>
                </section>
                <section id="principles" class="public-principles" aria-labelledby="principles-heading"><p class="public-eyebrow">One system for the work that matters</p><h2 id="principles-heading">Clear status. Visible progress. Reliable history.</h2><div class="public-principles-grid"><article><i class="ph ph-target" aria-hidden="true"></i><h3>Explicit status</h3><p>Every task has a clear place, so you always know what’s next.</p></article><article><i class="ph ph-chart-line-up" aria-hidden="true"></i><h3>Visible progress</h3><p>See what is moving, what is blocked, and what needs attention.</p></article><article><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i><h3>Recorded history</h3><p>Keep the context behind every meaningful change and decision.</p></article></div></section>
                <section id="auth" class="public-cta" aria-labelledby="cta-heading"><div><p class="public-eyebrow">Get started in seconds</p><h2 id="cta-heading">One account.<br>All your work.</h2></div><p>Create your PlanOps account and start making progress today.</p><a href="{{ route('register') }}" class="planops-public-button planops-public-button-primary">Get Started <span aria-hidden="true">→</span></a></section>
            </main>
            <footer class="public-footer"><x-application-logo /><span>Track the work. See the progress.</span><span>© {{ date('Y') }} PlanOps</span></footer>
        </div>
    </body>
</html>
