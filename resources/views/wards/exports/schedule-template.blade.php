{!! '<?xml version="1.0"?>' !!}
{!! '<?mso-application progid="Excel.Sheet"?>' !!}
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:excel="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>SmartWard</Author>
  <LastAuthor>SmartWard</LastAuthor>
  <Created>{{ now()->toIso8601String() }}</Created>
  <Version>16.00</Version>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" excel:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="sHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font ss:FontName="Calibri" excel:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#4472C4" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="sDate">
   <NumberFormat ss:Format="Short Date"/>
  </Style>
  <Style ss:ID="sLocked">
   <Protection ss:Protected="1"/>
   <Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Roster">
  <Table ss:ExpandedColumnCount="4" ss:ExpandedRowCount="{{ count($rows) + 1 }}" excel:FullColumns="1"
   excel:FullRows="1" ss:DefaultRowHeight="15">
   <Column ss:Width="80"/>
   <Column ss:Width="100"/>
   <Column ss:Width="60"/>
   <Column ss:Width="200"/>
   <Row ss:AutoFitHeight="0">
    <Cell ss:StyleID="sHeader"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="sHeader"><Data ss:Type="String">Bed</Data></Cell>
    <Cell ss:StyleID="sHeader"><Data ss:Type="String">Shift</Data></Cell>
    <Cell ss:StyleID="sHeader"><Data ss:Type="String">Nurse</Data></Cell>
   </Row>
   @foreach($rows as $row)
   <Row ss:AutoFitHeight="0">
    <Cell ss:StyleID="sDate"><Data ss:Type="String">{{ $row['date'] }}</Data></Cell>
    <Cell><Data ss:Type="String">{{ $row['bed'] }}</Data></Cell>
    <Cell><Data ss:Type="String">{{ $row['shift'] }}</Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
   </Row>
   @endforeach
  </Table>
  <excel:WorksheetOptions>
   <excel:PageLayoutZoom>0</excel:PageLayoutZoom>
   <excel:Selected/>
   <excel:Panes>
    <excel:Pane>
     <excel:Number>3</excel:Number>
     <excel:ActiveRow>1</excel:ActiveRow>
    </excel:Pane>
   </excel:Panes>
   <excel:ProtectObjects>False</excel:ProtectObjects>
   <excel:ProtectScenarios>False</excel:ProtectScenarios>
  </excel:WorksheetOptions>
  <excel:DataValidation>
   <excel:Range>R2C4:R{{ count($rows) + 1 }}C4</excel:Range>
   <excel:Type>List</excel:Type>
   <excel:Value>NurseList</excel:Value>
  </excel:DataValidation>
 </Worksheet>
 <Worksheet ss:Name="Nurses">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="{{ $nurses->count() }}" excel:FullColumns="1"
   excel:FullRows="1" ss:DefaultRowHeight="15">
   <Column ss:Width="200"/>
   @foreach($nurses as $nurse)
   <Row ss:AutoFitHeight="0">
    <Cell><Data ss:Type="String">{{ $nurse->name }} [{{ $nurse->id }}]</Data></Cell>
   </Row>
   @endforeach
  </Table>
  <excel:WorksheetOptions>
   <excel:Visible>SheetHidden</excel:Visible>
  </excel:WorksheetOptions>
 </Worksheet>
 <Names>
  <NamedRange ss:Name="NurseList" ss:RefersTo="=Nurses!R1C1:R{{ max(1, $nurses->count()) }}C1"/>
 </Names>
</Workbook>
