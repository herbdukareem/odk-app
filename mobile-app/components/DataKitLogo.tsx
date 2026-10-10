import { Svg, Rect, Text as SvgText } from 'react-native-svg';
import { View } from 'react-native';

type DataKitLogoProps = {
  dark?: boolean;
  compact?: boolean;
  large?: boolean;
};

export default function DataKitLogo({ dark = false, compact = false, large = false }: DataKitLogoProps) {
  const width = compact ? 38 : large ? 214 : 172;
  const height = compact ? 38 : large ? 52 : 42;
  const iconSize = compact ? 38 : 38;

  return (
    <View style={{ width, height, flexDirection: 'row', alignItems: 'center' }}>
      <Svg width={iconSize} height={iconSize} viewBox="0 0 64 64">
        <Rect width="64" height="64" rx="16" fill={dark ? '#dff0e5' : '#0e5a3a'} />
        <Rect x="15" y="14" width="34" height="8" rx="4" fill={dark ? '#0b4a2f' : '#fff'} />
        <Rect x="15" y="28" width="41" height="8" rx="4" fill={dark ? '#0b4a2f' : '#fff'} />
        <Rect x="15" y="42" width="34" height="8" rx="4" fill={dark ? '#0b4a2f' : '#fff'} />
      </Svg>
      {!compact ? (
        <View style={{ marginLeft: 8 }}>
          <Svg width="126" height="27" viewBox="0 0 126 27">
            <SvgText x="0" y="21" fill={dark ? '#fff' : '#0b3d2a'} fontSize="21" fontWeight="500">Data</SvgText>
            <SvgText x="47" y="21" fill={dark ? '#8be0b5' : '#0e5a3a'} fontSize="21" fontWeight="800">Kit</SvgText>
          </Svg>
          <Svg width="126" height="12" viewBox="0 0 126 12">
            <SvgText x="1" y="9" fill={dark ? '#a9cbb9' : '#55655c'} fontSize="8">Quality Assurance</SvgText>
          </Svg>
        </View>
      ) : null}
    </View>
  );
}
