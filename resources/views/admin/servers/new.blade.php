@extends('layouts.admin')

@section('title')
    Create Server
@endsection

@section('content-header')
    <h1>Workspace / Create server<small>Select runtime language, allocate resources, and deploy your application.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li class="active">Create Server</li>
    </ol>
@endsection

@section('content')
<style>
    /* Botkeep-style Dark Grid & Sliders */
    .runtime-grid-container {
        margin-bottom: 24px;
    }
    .runtime-grid-title {
        font-size: 16px;
        font-weight: 700;
        color: #FFFFFF;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .runtime-grid-subtitle {
        font-size: 13px;
        color: #9CA3AF;
        margin: 0 0 16px 0;
    }
    .runtime-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    @media (max-width: 1200px) {
        .runtime-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .runtime-grid { grid-template-columns: 1fr; }
    }
    .runtime-card {
        background: rgba(26, 26, 26, 0.95);
        border: 1.5px solid #333333;
        border-radius: 12px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        position: relative;
        display: flex;
        flex-direction: column;
        user-select: none;
    }
    .runtime-card:hover {
        border-color: #4b5563;
        background: rgba(32, 32, 32, 0.95);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    }
    .runtime-card.active {
        border-color: #3b82f6 !important;
        background: rgba(59, 130, 246, 0.09) !important;
        box-shadow: 0 0 0 1px #3b82f6, 0 8px 28px rgba(59, 130, 246, 0.25) !important;
    }
    .runtime-card .badge-selected {
        display: none;
        position: absolute;
        top: 14px;
        right: 14px;
        background: #3b82f6;
        color: #ffffff;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        align-items: center;
        justify-content: center;
        font-size: 11px;
    }
    .runtime-card.active .badge-selected {
        display: flex;
    }
    .runtime-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.04);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }
    .runtime-icon {
        width: 28px;
        height: 28px;
    }
    .runtime-name {
        font-size: 16px;
        font-weight: 700;
        color: #FFFFFF;
        margin: 0 0 4px 0;
    }
    .runtime-desc {
        font-size: 13px;
        color: #9CA3AF;
        margin: 0;
        line-height: 1.4;
    }
    /* Resources Panel */
    .resource-panel {
        background: rgba(26, 26, 26, 0.95);
        border: 1.5px solid #333333;
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 24px;
    }
    .resource-header {
        font-size: 16px;
        font-weight: 700;
        color: #FFFFFF;
        margin-bottom: 4px;
    }
    .resource-sub {
        font-size: 12px;
        color: #9CA3AF;
        margin-bottom: 18px;
    }
    .resource-slider-group {
        margin-bottom: 20px;
    }
    .slider-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .slider-label {
        font-size: 13.5px;
        font-weight: 600;
        color: #E5E7EB;
    }
    .slider-value-pill {
        font-size: 13px;
        font-weight: 700;
        color: #93c5fd;
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        padding: 2px 10px;
        border-radius: 6px;
    }
    .custom-range {
        width: 100%;
        height: 6px;
        border-radius: 3px;
        background: #333333;
        outline: none;
        -webkit-appearance: none;
        accent-color: #3b82f6;
    }
    .custom-range::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #3b82f6;
        cursor: pointer;
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.6);
    }
    .range-marks {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #6B7280;
        margin-top: 4px;
    }
</style>

<form action="{{ route('admin.servers.new') }}" method="POST" id="serverCreationForm">
    <!-- Row 1: Language Selection Grid & Resources Side by Side -->
    <div class="row">
        <!-- Left: Language Cards Grid -->
        <div class="col-lg-8 col-md-7 col-xs-12">
            <div class="box" style="border: none; background: transparent; box-shadow: none;">
                <div class="runtime-grid-container">
                    <h3 class="runtime-grid-title"><i class="fa fa-cubes text-primary"></i> Select Application Runtime / Language</h3>
                    <p class="runtime-grid-subtitle">Choose the runtime stack for your server. Pre-configured starter files and execution engines are automatically set up.</p>

                    <div class="runtime-grid" id="runtimeLanguageGrid">
                        <!-- 1. Node.js -->
                        <div class="runtime-card active" data-nest="5" data-egg="15" data-runtime="node" data-name="Node.js" data-startup="node index.js">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 256 289">
                                    <path fill="#539E43" d="M128 0L256 73.9V215.1L128 289L0 215.1V73.9L128 0Z"/>
                                    <path fill="#FFFFFF" d="M149.3 189.6C147.2 189.6 145.4 188.7 144.1 187.1L120.5 158.4V185C120.5 187.5 118.5 189.6 115.9 189.6H109.1C106.6 189.6 104.5 187.5 104.5 185V113.8C104.5 111.3 106.6 109.2 109.1 109.2H115.9C118.5 109.2 120.5 111.3 120.5 113.8V140.4L144.1 111.7C145.4 110.1 147.2 109.2 149.3 109.2H157.9C161 109.2 163.2 112.3 161.9 115.1L134.6 149.4L162.2 183.7C163.5 186.5 161.3 189.6 158.2 189.6H149.3Z"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Node.js</h4>
                            <p class="runtime-desc">JavaScript apps, APIs and bots</p>
                        </div>

                        <!-- 2. Python -->
                        <div class="runtime-card" data-nest="5" data-egg="16" data-runtime="python" data-name="Python" data-startup="python3 app.py">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 110 110">
                                    <path fill="#3776AB" d="M54.5 10C34.8 10 36.1 18.5 36.1 18.5L36.2 27.3H55V29.9H28.4C14.7 29.9 10 39.8 10 54.3C10 68.8 18 69.5 22.4 69.5H28.3V61.3C28.3 52 36.2 44.5 45.4 44.5H64.3C71.7 44.5 77.7 38.3 77.7 30.8V18.5C77.7 18.5 74.3 10 54.5 10ZM45.6 15.9C48.2 15.9 50.3 18 50.3 20.6C50.3 23.2 48.2 25.3 45.6 25.3C43 25.3 40.9 23.2 40.9 20.6C40.9 18 43 15.9 45.6 15.9Z"/>
                                    <path fill="#FFD43B" d="M55.5 100C75.2 100 73.9 91.5 73.9 91.5L73.8 82.7H55V80.1H81.6C95.3 80.1 100 70.2 100 55.7C100 41.2 92 40.5 87.6 40.5H81.7V48.7C81.7 58 73.8 65.5 64.6 65.5H45.7C38.3 65.5 32.3 71.7 32.3 79.2V91.5C32.3 91.5 35.7 100 55.5 100ZM64.4 94.1C61.8 94.1 59.7 92 59.7 89.4C59.7 86.8 61.8 84.7 64.4 84.7C67 84.7 69.1 86.8 69.1 89.4C69.1 92 67 94.1 64.4 94.1Z"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Python</h4>
                            <p class="runtime-desc">Python apps, workers and bots</p>
                        </div>

                        <!-- 3. Java -->
                        <div class="runtime-card" data-nest="5" data-egg="21" data-runtime="java" data-name="Java" data-startup="java -jar server.jar">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 256 348">
                                    <path fill="#5382A1" d="M84.5 289.4c-35-1.7-65.7-16-65.7-16s24.4 8.7 54.3 10.6c34.9 2.2 78.4 1.3 118.8-12.7 0 0-11 5.4-27 9.8-31.4 8.7-52.6 9.4-80.4 8.3z"/>
                                    <path fill="#E76F00" d="M107.5 174.5c22.6-32.4 13.9-52.2 5.5-74.8-5.7-15.4-8.8-29.2-2.1-41.5 5.5 10.7 7.7 20.9 6.2 30.6-2.5 16-12.5 31.9-19.4 47.9-10.4 24.1-8.5 45.4 9.8 37.8z"/>
                                    <path fill="#E76F00" d="M129.5 77.2c12.5-16.7 5.7-32.9.8-48.4-1.6-5.1-2.9-10.1-3.6-15 0 0 16.7 15.3 11.6 34.6-4.5 16.9-13.6 24.8-8.8 28.8z"/>
                                    <path fill="#5382A1" d="M188.5 240.5s15.3-8.8 8.8-19.7c-6-10-21.6-14-34.9-17 10 3.3 16.6 8.5 14.5 13.3-3.6 8.2-19.5 13.8-35.4 18-38.3 10.1-86.4 12.3-125.7 1.5 0 0 17 6.4 49 9.8 45.7 4.9 92.4 2.8 123.7-5.9z"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Java</h4>
                            <p class="runtime-desc">JVM applications</p>
                        </div>

                        <!-- 4. PHP -->
                        <div class="runtime-card" data-nest="5" data-egg="17" data-runtime="php" data-name="PHP" data-startup="php -S 0.0.0.0:5000 index.php">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 256 137">
                                    <ellipse cx="128" cy="68.5" rx="124" ry="65" fill="#777BB4"/>
                                    <path fill="#FFFFFF" d="M63 42.5h-22l-11 52h14l3.5-16.5h10.5c15 0 24-8.5 27.5-23.5 2.5-11-2-12-22.5-12zm-3.5 18c-1.5 6.5-6.5 9-13.5 9h-6l3.5-16.5h6c7.5 0 11.5 1.5 10 7.5zm70-18h-14l-11 52h14l4.5-21h18l-4.5 21h14l11-52h-14l-4.5 20h-18l4.5-20zm76 0h-22l-11 52h14l3.5-16.5h10.5c15 0 24-8.5 27.5-23.5 2.5-11-2-12-22.5-12zm-3.5 18c-1.5 6.5-6.5 9-13.5 9h-6l3.5-16.5h6c7.5 0 11.5 1.5 10 7.5z"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">PHP</h4>
                            <p class="runtime-desc">PHP scripts and workers</p>
                        </div>

                        <!-- 5. Go -->
                        <div class="runtime-card" data-nest="5" data-egg="20" data-runtime="go" data-name="Go" data-startup="go run main.go">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 256 96">
                                    <path fill="#00ADD8" d="M16 28h224v40H16z" rx="8"/>
                                    <text x="128" y="58" fill="#FFFFFF" font-family="sans-serif" font-size="34" font-weight="900" text-anchor="middle">GO</text>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Go</h4>
                            <p class="runtime-desc">Go applications and APIs</p>
                        </div>

                        <!-- 6. Rust -->
                        <div class="runtime-card" data-nest="5" data-egg="22" data-runtime="rust" data-name="Rust" data-startup="cargo run --release">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 106 106">
                                    <circle cx="53" cy="53" r="48" fill="#DEA584" stroke="#000000" stroke-width="4"/>
                                    <text x="53" y="65" fill="#000000" font-family="sans-serif" font-size="44" font-weight="900" text-anchor="middle">R</text>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Rust</h4>
                            <p class="runtime-desc">Compiled Rust applications</p>
                        </div>

                        <!-- 7. C++ -->
                        <div class="runtime-card" data-nest="5" data-egg="23" data-runtime="cpp" data-name="C++" data-startup="./main">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 128 128">
                                    <path fill="#00599C" d="M117.5 37.5L67.5 8.7a7.5 7.5 0 0 0-7.5 0L10.5 37.5a7.5 7.5 0 0 0-3.8 6.5v57.6a7.5 7.5 0 0 0 3.8 6.5l49.5 28.8a7.5 7.5 0 0 0 7.5 0l49.5-28.8a7.5 7.5 0 0 0 3.8-6.5V44a7.5 7.5 0 0 0-3.8-6.5z"/>
                                    <text x="64" y="78" fill="#FFFFFF" font-family="sans-serif" font-size="34" font-weight="900" text-anchor="middle">C++</text>
                                </svg>
                            </div>
                            <h4 class="runtime-name">C++</h4>
                            <p class="runtime-desc">Compiled C++ applications</p>
                        </div>

                        <!-- 8. Lua / Luvit -->
                        <div class="runtime-card" data-nest="5" data-egg="24" data-runtime="lua" data-name="Lua / Luvit" data-startup="luvit server.lua">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 128 128">
                                    <circle cx="64" cy="64" r="56" fill="#000080"/>
                                    <circle cx="86" cy="42" r="16" fill="#FFFFFF"/>
                                    <text x="56" y="86" fill="#FFFFFF" font-family="sans-serif" font-size="28" font-weight="800">Lua</text>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Lua / Luvit</h4>
                            <p class="runtime-desc">Lua applications using Luvit</p>
                        </div>

                        <!-- 9. Node.js + Nodemon -->
                        <div class="runtime-card" data-nest="5" data-egg="25" data-runtime="node" data-name="Node.js + Nodemon" data-startup="npx nodemon index.js">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 128 128">
                                    <path fill="#76D04B" d="M64 8l48 24v56l-48 32-48-32V32L64 8z"/>
                                    <text x="64" y="80" fill="#FFFFFF" font-family="sans-serif" font-size="48" font-weight="900" text-anchor="middle">N</text>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Node.js + Nodemon</h4>
                            <p class="runtime-desc">Node.js with a file watcher</p>
                        </div>

                        <!-- 10. Bun -->
                        <div class="runtime-card" data-nest="5" data-egg="19" data-runtime="bun" data-name="Bun" data-startup="bun run index.ts">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 128 128">
                                    <circle cx="64" cy="64" r="54" fill="#FBF0DF" stroke="#292929" stroke-width="4"/>
                                    <circle cx="50" cy="56" r="6" fill="#292929"/>
                                    <circle cx="78" cy="56" r="6" fill="#292929"/>
                                    <path d="M54 74c5 5 15 5 20 0" stroke="#E57373" stroke-width="4" fill="none" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Bun</h4>
                            <p class="runtime-desc">JavaScript & TypeScript runtime</p>
                        </div>

                        <!-- 11. Static Website -->
                        <div class="runtime-card" data-nest="5" data-egg="18" data-runtime="static" data-name="Static Website" data-startup="npx serve -s -l 5000">
                            <div class="badge-selected"><i class="fa fa-check"></i></div>
                            <div class="runtime-icon-wrapper">
                                <svg class="runtime-icon" viewBox="0 0 128 128">
                                    <path fill="#E34F26" d="M19 12l9 100 36 10 36-10 9-100H19z"/>
                                    <path fill="#EF652A" d="M64 113.6l28.6-8 7.5-83.6H64v91.6z"/>
                                    <path fill="#FFFFFF" d="M64 45.4H42.7l1.7 18.2H64v15H45.7l1.7 18.8 16.6 4.6V113l-28.5-8-2-22.6h12.5l1.2 13.5 16.8 4.5v-15.6z"/>
                                </svg>
                            </div>
                            <h4 class="runtime-name">Static Website</h4>
                            <p class="runtime-desc">HTML, CSS & Frontend apps</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: 04 / Resources Panel (Matching Botkeep) -->
        <div class="col-lg-4 col-md-5 col-xs-12">
            <div class="resource-panel">
                <div class="resource-header">04 / Resources</div>
                <div class="resource-sub">Assign RAM, CPU and Storage to this server instance.</div>

                <!-- RAM Slider -->
                <div class="resource-slider-group">
                    <div class="slider-meta">
                        <span class="slider-label"><i class="fa fa-microchip text-primary"></i> RAM</span>
                        <span class="slider-value-pill" id="ramValuePill">512 MB</span>
                    </div>
                    <input type="range" class="custom-range" id="ramRangeSlider" min="256" max="8192" step="128" value="512" oninput="syncRam(this.value)">
                    <div class="range-marks">
                        <span>256 MB</span>
                        <span>2 GB</span>
                        <span>4 GB</span>
                        <span>8 GB</span>
                    </div>
                </div>

                <!-- CPU Slider -->
                <div class="resource-slider-group">
                    <div class="slider-meta">
                        <span class="slider-label"><i class="fa fa-dashboard text-primary"></i> CPU Limit</span>
                        <span class="slider-value-pill" id="cpuValuePill">100% (1.0 vCore)</span>
                    </div>
                    <input type="range" class="custom-range" id="cpuRangeSlider" min="25" max="400" step="25" value="100" oninput="syncCpu(this.value)">
                    <div class="range-marks">
                        <span>25%</span>
                        <span>100% (1 Core)</span>
                        <span>200%</span>
                        <span>400% (4 Cores)</span>
                    </div>
                </div>

                <!-- Storage Slider -->
                <div class="resource-slider-group">
                    <div class="slider-meta">
                        <span class="slider-label"><i class="fa fa-hdd-o text-primary"></i> Storage</span>
                        <span class="slider-value-pill" id="diskValuePill">1024 MB (1 GB)</span>
                    </div>
                    <input type="range" class="custom-range" id="diskRangeSlider" min="512" max="20480" step="512" value="1024" oninput="syncDisk(this.value)">
                    <div class="range-marks">
                        <span>512 MB</span>
                        <span>5 GB</span>
                        <span>10 GB</span>
                        <span>20 GB</span>
                    </div>
                </div>
            </div>

            <!-- Server Core Settings (Card) -->
            <div class="box" style="border-radius: 12px; background: rgba(26, 26, 26, 0.95); border: 1.5px solid #333333; box-shadow: none;">
                <div class="box-header with-border" style="border-color: #333333;">
                    <h3 class="box-title" style="color: #FFFFFF;"><i class="fa fa-gear text-primary"></i> Core Deployment Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" style="color: #E5E7EB;">Server Name</label>
                        <input type="text" class="form-control" id="pName" name="name" value="{{ old('name', 'My Node.js Server') }}" placeholder="e.g. My Application" required>
                    </div>

                    <div class="form-group">
                        <label for="pUserId" style="color: #E5E7EB;">Server Owner</label>
                        <select id="pUserId" name="owner_id" class="form-control" style="width: 100%;"></select>
                    </div>

                    <div class="form-group">
                        <label for="pNodeId" style="color: #E5E7EB;">Node</label>
                        <select name="node_id" id="pNodeId" class="form-control">
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->long }} ({{ $location->short }})">
                                @foreach($location->nodes as $node)
                                    <option value="{{ $node->id }}"
                                        @if($node->id === old('node_id')) selected="selected" @endif
                                    >{{ $node->name }}</option>
                                @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pAllocation" style="color: #E5E7EB;">Default Allocation (Port)</label>
                        <select id="pAllocation" name="allocation_id" class="form-control"></select>
                    </div>

                    <div class="checkbox checkbox-primary" style="margin-top: 14px;">
                        <input id="pStartOnCreation" name="start_on_completion" type="checkbox" checked value="1" />
                        <label for="pStartOnCreation" class="strong" style="color: #E5E7EB;">Start Server when Installed</label>
                    </div>

                    <div style="margin-top: 20px;">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight: 700; border-radius: 8px; font-size: 15px; padding: 12px;">
                            <i class="fa fa-rocket"></i> Deploy Application Server
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden / Synced Backend Fields so standard Pterodactyl Controller creates cleanly -->
    <div style="display: none;">
        <!-- Hidden Inputs for Memory / CPU / Disk synced with Sliders -->
        <input type="text" id="pMemory" name="memory" value="{{ old('memory', 512) }}" />
        <input type="text" id="pCPU" name="cpu" value="{{ old('cpu', 100) }}" />
        <input type="text" id="pDisk" name="disk" value="{{ old('disk', 1024) }}" />
        <input type="text" id="pSwap" name="swap" value="{{ old('swap', 0) }}" />
        <input type="text" id="pIO" name="io" value="{{ old('io', 500) }}" />
        <input type="checkbox" id="pOomDisabled" name="oom_disabled" value="0" />

        <!-- Limits -->
        <input type="text" id="pDatabaseLimit" name="database_limit" value="{{ old('database_limit', 0) }}"/>
        <input type="text" id="pAllocationLimit" name="allocation_limit" value="{{ old('allocation_limit', 0) }}"/>
        <input type="text" id="pBackupLimit" name="backup_limit" value="{{ old('backup_limit', 0) }}"/>
        <select id="pAllocationAdditional" name="allocation_additional[]" multiple class="form-control"></select>

        <!-- Nests & Eggs -->
        <select id="pNestId" name="nest_id" class="form-control">
            @foreach($nests as $nest)
                <option value="{{ $nest->id }}"
                    @if($nest->id === old('nest_id', 5))
                        selected="selected"
                    @endif
                >{{ $nest->name }}</option>
            @endforeach
        </select>
        <select id="pEggId" name="egg_id" class="form-control"></select>
        <select id="pDefaultContainer" name="image" class="form-control"></select>
        <input id="pDefaultContainerCustom" name="custom_image" value="{{ old('custom_image') }}" />
        <input type="text" id="pStartup" name="startup" value="{{ old('startup', 'node index.js') }}" />
        <input type="checkbox" id="pSkipScripting" name="skip_scripts" value="1" checked />
        <div id="appendVariablesTo"></div>
        <textarea id="pDescription" name="description">{{ old('description', 'Native Server Deployment') }}</textarea>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}

    <script type="application/javascript">
        // Sync sliders to hidden input values
        function syncRam(val) {
            document.getElementById('pMemory').value = val;
            var text = val >= 1024 ? (val / 1024).toFixed(val % 1024 === 0 ? 0 : 1) + ' GB' : val + ' MB';
            document.getElementById('ramValuePill').textContent = text;
        }

        function syncCpu(val) {
            document.getElementById('pCPU').value = val;
            var vCores = (val / 100).toFixed(val % 100 === 0 ? 0 : 1);
            document.getElementById('cpuValuePill').textContent = val + '% (' + vCores + ' vCore)';
        }

        function syncDisk(val) {
            document.getElementById('pDisk').value = val;
            var text = val >= 1024 ? (val / 1024).toFixed(val % 1024 === 0 ? 0 : 1) + ' GB' : val + ' MB';
            document.getElementById('diskValuePill').textContent = text;
        }

        function serviceVariablesUpdated(eggId, ids) {
            @if (old('egg_id'))
                if (eggId != '{{ old('egg_id') }}') return;
                @if (old('environment'))
                    @foreach (old('environment') as $key => $value)
                        $('#' + ids['{{ $key }}']).val('{{ $value }}');
                    @endforeach
                @endif
            @endif
        }
    </script>

    {!! Theme::js('js/admin/new-server.js?v=20220530') !!}

    <script type="application/javascript">
        $(document).ready(function() {
            // Language Selection Card Click Handler
            $('.runtime-card').on('click', function() {
                $('.runtime-card').removeClass('active');
                $(this).addClass('active');

                var nestId = $(this).data('nest') || 5;
                var eggId = $(this).data('egg');
                var startup = $(this).data('startup');
                var name = $(this).data('name');

                // Auto update server name if user hasn't typed a custom one
                var nameInput = $('#pName');
                if (!nameInput.val() || nameInput.val().startsWith('My ') || nameInput.val().endsWith('Server')) {
                    nameInput.val('My ' + name + ' Server');
                }

                // Switch Nest and Egg
                if ($('#pNestId').val() != nestId) {
                    $('#pNestId').val(nestId).change();
                }

                setTimeout(function() {
                    $('#pEggId').val(eggId).change();
                    if (startup) {
                        $('#pStartup').val(startup);
                    }
                }, 150);
            });

            // Initialize Owner select2
            @if (old('owner_id'))
                $.ajax({
                    url: '/admin/users/accounts.json?user_id={{ old('owner_id') }}',
                    dataType: 'json',
                }).then(function (data) {
                    initUserIdSelect([ data ]);
                });
            @else
                initUserIdSelect();
            @endif

            // Trigger default selections
            $('#pNestId').val('5').change();
            setTimeout(function() {
                $('#pEggId').val('15').change();
            }, 200);

            // Sync sliders initial values
            syncRam($('#ramRangeSlider').val());
            syncCpu($('#cpuRangeSlider').val());
            syncDisk($('#diskRangeSlider').val());
        });
    </script>
@endsection
