import { geoEqualEarth } from 'd3-geo';
import type { FeatureCollection } from 'geojson';
import countries from 'i18n-iso-countries';
import { useMemo } from 'react';
import { ComposableMap, Geographies, Geography } from 'react-simple-maps';
import { feature } from 'topojson-client';
import type { GeometryCollection, Topology } from 'topojson-specification';
import worldTopology from 'world-atlas/countries-110m.json';

import { countryFlag, countryName } from '@/lib/countries';

interface CountryCount {
    code: string;
    count: number;
}

interface WorldMapProps {
    countries: CountryCount[];
    /** The country under the pointer, here or in a list beside the map. */
    hovered: string | null;
    onHover: (code: string | null) => void;
    unit?: [string, string];
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

/** One hue, light to dark (dark to bright on a dark page): more installs, stronger blue. */
function shade(ratio: number): string {
    return `color-mix(in oklab, var(--viz-blue) ${Math.round(18 + ratio * 82)}%, var(--muted))`;
}

/**
 * Installs per country. The shading follows the square root of the count: one market
 * (the US) is several times the next, and a linear scale would leave every other
 * country the same pale shade.
 */
export function WorldMap({ countries: counts, hovered, onHover, unit = ['install', 'installs'] }: WorldMapProps) {
    const countByCode = useMemo(() => new Map(counts.map((country) => [country.code, country.count])), [counts]);
    const codeByNumeric = useMemo(
        () => new Map(counts.map((country) => [countries.alpha2ToNumeric(country.code) ?? '', country.code])),
        [counts],
    );
    const maxCount = useMemo(() => Math.max(1, ...counts.map((country) => country.count)), [counts]);
    const hoveredCount = hovered ? (countByCode.get(hovered) ?? 0) : 0;

    return (
        <div className="flex flex-col gap-3">
            <div className="relative">
                <ComposableMap projection={projection} width={MAP_WIDTH} height={MAP_HEIGHT} className="h-auto w-full">
                    <Geographies geography={geography}>
                        {({ geographies }) =>
                            geographies.map((geo) => {
                                const code = codeByNumeric.get(String(geo.id)) ?? countries.numericToAlpha2(String(geo.id)) ?? null;
                                const count = code ? (countByCode.get(code) ?? 0) : 0;
                                const isHovered = code !== null && code === hovered;

                                return (
                                    <Geography
                                        key={geo.rsmKey}
                                        geography={geo}
                                        onMouseEnter={() => count > 0 && onHover(code)}
                                        onMouseLeave={() => onHover(null)}
                                        style={{
                                            fill: count > 0 ? shade(Math.sqrt(count / maxCount)) : 'var(--muted)',
                                            stroke: isHovered ? 'var(--foreground)' : 'var(--card)',
                                            strokeWidth: isHovered ? 1.4 : 0.5,
                                            outline: 'none',
                                            cursor: count > 0 ? 'pointer' : 'default',
                                        }}
                                    />
                                );
                            })
                        }
                    </Geographies>
                </ComposableMap>

                <div className="pointer-events-none absolute top-0 left-0 min-h-9 text-sm">
                    {hovered ? (
                        <div className="flex flex-col rounded-md border bg-popover/90 px-2 py-1 leading-tight shadow-sm backdrop-blur-sm">
                            <span className="font-medium text-foreground">
                                {countryFlag(hovered)} {countryName(hovered)}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {numberFormatter.format(hoveredCount)} {hoveredCount === 1 ? unit[0] : unit[1]}
                            </span>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">Point at a country</span>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-2">
                <span className="text-[11px] text-muted-foreground tabular-nums">1</span>
                <div className="h-1.5 flex-1 rounded-full" style={{ background: `linear-gradient(to right, ${shade(0)}, ${shade(0.5)}, ${shade(1)})` }} />
                <span className="text-[11px] text-muted-foreground tabular-nums">{numberFormatter.format(maxCount)}</span>
            </div>
        </div>
    );
}
