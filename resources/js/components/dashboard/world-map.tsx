import { geoEqualEarth } from 'd3-geo';
import type { FeatureCollection } from 'geojson';
import countries from 'i18n-iso-countries';
import { useMemo, useState } from 'react';
import { ComposableMap, Geographies, Geography } from 'react-simple-maps';
import { feature } from 'topojson-client';
import type { GeometryCollection, Topology } from 'topojson-specification';
import worldTopology from 'world-atlas/countries-110m.json';

import { countryName } from '@/lib/countries';

interface CountryCount {
    code: string;
    count: number;
}

interface WorldMapProps {
    countries: CountryCount[];
    unplacedInstalls: number;
}

const topology = worldTopology as unknown as Topology<{ countries: GeometryCollection }>;
const ANTARCTICA = '010';
const geography: FeatureCollection = {
    type: 'FeatureCollection',
    features: (feature(topology, topology.objects.countries) as FeatureCollection).features.filter((f) => f.id !== ANTARCTICA),
};

const numberFormatter = new Intl.NumberFormat('en-US');

const MAP_WIDTH = 960;
const MAP_HEIGHT = 470;
const projection = geoEqualEarth().fitSize([MAP_WIDTH, MAP_HEIGHT], geography);

/**
 * Installs per country. The shading follows the square root of the count: one market
 * (the US) is several times the next, and a linear scale would leave every other
 * country the same pale shade.
 */
export function WorldMap({ countries: counts, unplacedInstalls }: WorldMapProps) {
    const [hoveredCode, setHoveredCode] = useState<string | null>(null);

    const countByCode = useMemo(() => new Map(counts.map((country) => [country.code, country.count])), [counts]);
    const codeByNumeric = useMemo(
        () => new Map(counts.map((country) => [countries.alpha2ToNumeric(country.code) ?? '', country.code])),
        [counts],
    );
    const maxCount = useMemo(() => Math.max(1, ...counts.map((country) => country.count)), [counts]);
    const hoveredCount = hoveredCode ? (countByCode.get(hoveredCode) ?? 0) : 0;

    return (
        <div className="flex flex-col gap-3">
            <div className="relative">
                <ComposableMap projection={projection} width={MAP_WIDTH} height={MAP_HEIGHT} className="h-auto max-h-[340px] w-full">
                    <Geographies geography={geography}>
                        {({ geographies }) =>
                            geographies.map((geo) => {
                                const code = codeByNumeric.get(String(geo.id)) ?? null;
                                const count = code ? (countByCode.get(code) ?? 0) : 0;
                                const ratio = Math.sqrt(count / maxCount);
                                const isHovered = code !== null && code === hoveredCode;

                                return (
                                    <Geography
                                        key={geo.rsmKey}
                                        geography={geo}
                                        onMouseEnter={() => code && setHoveredCode(code)}
                                        onMouseLeave={() => setHoveredCode(null)}
                                        style={{
                                            fill: count > 0 ? `color-mix(in oklch, var(--chart-1) ${Math.round(ratio * 100)}%, var(--chart-5))` : 'var(--muted)',
                                            stroke: isHovered ? 'var(--foreground)' : 'var(--background)',
                                            strokeWidth: isHovered ? 1.2 : 0.5,
                                            outline: 'none',
                                            cursor: 'default',
                                        }}
                                    />
                                );
                            })
                        }
                    </Geographies>
                </ComposableMap>

                <div className="pointer-events-none absolute top-0 left-0 text-sm">
                    {hoveredCode ? (
                        <div className="flex flex-col leading-tight">
                            <span className="font-medium text-foreground">{countryName(hoveredCode)}</span>
                            <span className="text-xs text-muted-foreground">
                                {numberFormatter.format(hoveredCount)} install{hoveredCount === 1 ? '' : 's'}
                            </span>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">Hover a country for its install count</span>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-2">
                <span className="text-[11px] text-muted-foreground">0</span>
                <div className="h-2 flex-1 rounded-full" style={{ background: 'linear-gradient(to right, var(--chart-5), var(--chart-1))' }} />
                <span className="text-[11px] text-muted-foreground">{numberFormatter.format(maxCount)}</span>
            </div>

            {unplacedInstalls > 0 && (
                <p className="text-xs text-muted-foreground">
                    {numberFormatter.format(unplacedInstalls)} install{unplacedInstalls === 1 ? '' : 's'} not shown — their device language
                    has no country (e.g. plain “en”).
                </p>
            )}
        </div>
    );
}
