<div id="muscle-results" class="grid lg:grid-cols-2 gap-6">
    <x-panel title="Weekly hard sets per muscle" subtitle="Classified vs RP landmarks (MEV/MAV/MRV)">
        <x-slot name="actions">
            <x-info title="MV · MEV · MAV · MRV" text="Weekly working sets per muscle. MV = maintain, MEV = minimum to grow, MAV = best-return zone, MRV = recovery ceiling. Stay between MEV and MAV to grow. 'Below maintenance' = too few sets to grow." />
        </x-slot>
        <x-muscle-volume-bars :items="$weeklySetsPerMuscle" />
    </x-panel>

    <div class="space-y-6">
        <x-panel title="Volume distribution" subtitle="Tonnage share per muscle">
            @if(count($volumePerMuscle))
                <x-chart type="polarArea" :height="300"
                    :labels="array_map(fn($x) => ucfirst(str_replace('_',' ',$x['muscle'])), $volumePerMuscle)"
                    :datasets="[['label' => 'Tonnage', 'data' => array_column($volumePerMuscle, 'tonnage')]]" />
            @else
                <p class="text-sm text-gray-500">No data.</p>
            @endif
        </x-panel>

        <x-panel title="Balance ratios">
            <x-balance-ratios :items="$balance" />
        </x-panel>
    </div>
</div>
