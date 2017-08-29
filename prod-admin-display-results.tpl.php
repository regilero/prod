<style>

div.prod-d3tooltip {
  position: absolute;
  text-align: center;
  border: 0px;
  line-height: 1;
  padding: 12px;
  background: rgba(0, 0, 0, 0.8);
  color: #fff;
  border-radius: 5px;
}

.grid-background {
  fill: #eee;
}

.grid line {
  stroke: #fff;
}

.grid .minor line {
  stroke-opacity: .5;
}

.axis line {
  stroke: #000;
}

.x-axis path,
.grid path {
  display: none;
}

</style>
<script type="text/javascript">

var margin = {top: 40, right: 80, bottom: 100, left: 80},
    width = 800 - margin.left - margin.right,
    height = 600 - margin.top - margin.bottom;

var bytesToString = function (bytes) {
    var fmt = d3.format('.0f');
    if (bytes < 1024) {
        return fmt(bytes) + 'B';
    } else if (bytes < 1048576) {
        return fmt(bytes / 1024) + 'kB';
    } else if (bytes < 1073741824) {
        return fmt(bytes / 1048576) + 'MB';
    } else {
        return fmt(bytes / 1073741824) + 'GB';
    }
}

//Create the export function - this will just export
//the first svg element it finds
function svgToCanvas(svg, w, h){
    console.log('********** TODO *******************');
    var img = new Image(),
        serializer = new XMLSerializer();
    var svgStr = serializer.serializeToString(svg.node());

    img.src = 'data:image/svg+xml;base64,'+window.btoa(svgStr);
    console.log(img.src);
    // You could also use the actual string without base64 encoding it:
    //img.src = "data:image/svg+xml;utf8," + svgStr;

    var canvas = document.createElement("canvas");
    document.body.appendChild(canvas);

    canvas.width = w;
    canvas.height = h;
    canvas.getContext("2d").drawImage(img,0,0,w,h);
    // Now save as png or whatever
};

// Tooltip info
//Define 'div' for tooltips
var tooltip_div = d3.select("body")
    .append("div")
    .attr("class", "prod-d3tooltip")
    .style("opacity", 0)
    .style("zIndex", "0")
    .on('click', function() {
        // hide tooltip on click
        tooltip_div.transition()
            .duration(500)
            .style('opacity', 0);
    });

function toolTiping(d) {
    tooltip_div.transition()
        .duration(500)
        .style('opacity', 0);
    tooltip_div.html(d.tooltip);
    tooltip_height = tooltip_div.node().getBoundingClientRect().height;
    tooltip_div.style("left", (d3.event.pageX +15 ) + "px")
        .style("top", (d3.event.pageY - (tooltip_height+20)) + "px")
        .style("zIndex", "1000");
    tooltip_div.transition()
        .duration(200)
        .style('opacity', .9);
}

//http://stackoverflow.com/a/20773846/550618
function doneForAll(transition, callback) {
    if (transition.size() === 0) { callback() }
    var n = 0;
    transition
        .each(function() { ++n; })
        .each("end", function() {
            if (!--n) callback.apply(this, arguments);
        });
}

function initGraph( graph ) {

    var graph_def = graph.def.graphic;

    graph.actions = graph.placeholder.append('div')
        .attr('class', 'prod-actions');

    graph.timeFormat = d3.time.format('%Y-%m-%dT%H:%M:%S');
    // Create Axis ----
    if ( graph_def.pivot ) {
        graph.x = d3.time.scale()
            .range([0, width]);
    } else {
        graph.x = d3.scale.ordinal()
            .rangeRoundBands([0, width], .1);
    }

    graph.yLeft = d3.scale.linear()
                .range([height, 0]);

    graph.yRight = d3.scale.linear()
                .range([height, 0]);

    graph.xAxis = d3.svg.axis()
        .scale(graph.x)
        .tickSize(1)
        .orient("bottom");
    if ( graph_def.pivot ) {
        graph.xAxis.tickFormat(graph.timeFormat)
            .innerTickSize(10)
            .ticks(30);
    }

    graph.yAxisLeft = d3.svg.axis()
        .scale(graph.yLeft)
        .tickSize(1)
        .innerTickSize(10)
        .ticks(10)
        .orient("left");
    if (graph.def.graphic.axis_y1.is_1024) {
        graph.yAxisLeft.tickFormat(bytesToString)
    }

    if ( graph_def.has_y2 ) {
        graph.yAxisRight = d3.svg.axis()
            .scale(graph.yRight)
            .tickSize(1)
            .innerTickSize(10)
            .ticks(10)
            .orient("right");
        if (graph.def.graphic.axis_y2.is_1024) {
            graph.yAxisRight.tickFormat(bytesToString)
        }
    }

    // Create the MAIN svg container
    graph.svg = graph.placeholder.append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
      .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    // Theming with a background rect
    graph.svg.append("rect")
        .attr("class", "grid-background")
        .attr("width", width)
        .attr("height", height);

    // Add x Axis
    graph.svg.append("g")
        .attr("class", "x axis x-axis")
        .attr("transform", "translate(0," + height + ")");

    // add right axis
    if ( graph_def.has_y2 ) {
        graph.svg.append("g")
            .attr("class", "y axis axisRight")
            .attr("transform", "translate(" + (width) + ",0)")
            .style("fill", 'red')
            .append("text")
                .attr("y", 6)
                .attr("dy", "-2em")
                .attr("dx", "2em")
                .style("text-anchor", "end")
                .text(graph.def.graphic.axis_y2.label);
    }

    // add left axis
    graph.svg.append("g")
        .attr("class", "y axis axisLeft")
        .attr("transform", "translate(0,0)")
        .append("text")
            .attr("y", 6)
            .attr("dy", "-2em")
            .style("text-anchor", "end")
            .text(graph.def.graphic.axis_y1.label);

    // theme grids
    graph.svg.append("g")
            .attr("class", "grid gridx")
            .attr("transform", "translate(0," + height + ")");
    graph.svg.append("g")
            .attr("class", "grid gridy")
            .attr("transform", "translate(0, 0)");

    // Export button
    var exporter = graph.actions.append("button")
        .text(graph.def.buttons.save)
        .on("click",function() {
            svgToCanvas(graph.svg,800,600)
        });

    // Prev / next buttons
    graph.prev = graph.actions.append("button")
        .text(graph.def.buttons.prev)
        .on("click",function() {
            var cur_val = graph.filters.pagecounter.property('value');
            if ( cur_val > 1 ) {
                graph.filters.pagecounter.attr('value',cur_val - 1);
                loadSomeData( graph );
            }
        });
    graph.next = graph.actions.append("button")
        .text(graph.def.buttons.next)
        .on("click",function() {
            graph.filters.pagecounter.attr('value',parseInt(graph.filters.pagecounter.property('value'),10) + 1);
            loadSomeData( graph );
        });

    // Connect external filters
    if (! graph.filterszone.empty() ) {
        graph.filters = {
            selector : graph.filterszone.select('select[name="nbelt"]'),
            pagecounter : graph.filterszone.select('input[name="page"]'),
            sort : graph.filterszone.select('select[name="sort"]')
        }

        graph.filters.selector.on('change', function(){
            graph.filters.pagecounter.attr('value',1);
            loadSomeData( graph );
        });

        graph.filters.sort.on('change', function(){
            graph.filters.pagecounter.attr('value',1);
            loadSomeData( graph );
        });
    }

    initTable( graph );
}

function initTable( graph ) {

    if ( graph.tablezone.empty() ) return false;

    var table_def = graph.def.table;

    graph.table = { main : graph.tablezone.append('table')
        .attr('class', 'table table-striped prod-report-table')
    };

    graph.table.main.append('caption').text(table_def.caption);

    var head = graph.table.main.append('thead').append('tr');

    graph.table.body = graph.table.main.append('tbody');

    table_def.columns.forEach( function(h) {
         head.append('th')
             .attr('class', 'prod-report-table-header')
             .text(h.header);
    });

}


function handleJsonData( data, graph ) {

    var graph_def = graph.def.graphic;
    var tooltip_def = graph_def.tooltip;
    var rows = data.rows;

    var y1_key = graph_def.axis_y1.key;
    if ( graph_def.has_y2 ) {
        var y2_key = graph_def.axis_y2.key;
    }
    var x_key = graph_def.axis_x.key;

    var color = d3.scale.linear()
        .domain([0, 2])
        .range(["#aad", "#556"]);

    graph.color = d3.scale.linear()
        .range(["#333399", "red"]);


    // We may have some new datas, start by hiding the tooltip which may be active
    tooltip_div.transition()
            .duration(500)
            .style('opacity', 0)
            .style("zIndex", "0");

    // build title and content of tooltip in data records
    if (!graph_def.pivot) {
        rows.forEach( function (d) {
            var title='', content='';
            tooltip_def.title.forEach(function(title_key) {
                if (''==title) {
                    title = d[title_key];
                } else {
                    title += ' ' + d[title_key];
                }
            });
            tooltip_def.content.forEach(function(infos) {
                content += '<br/>' + infos.legend + ': ' + d[infos.key];
            });
            d.tooltip = '<strong>' + title + '</strong>' + content;
        });
    }

    // Transpose the data into layers for stacked layers
    if (graph_def.stacked) {
        var layers = d3.layout.stack()(
            graph_def.stacked_layers.map(
                function( layer ) {
                    return rows.map(function(d) {
                        // transposition
                        return {
                            x: d[x_key],
                            y: +d[layer],
                            tooltip: d.tooltip
                        };
                    });
                }
            )
        );
    } else {
        if (graph_def.pivot) {
            // data is alreay pivoted
            var stack = d3.layout.stack()
                .values(function( layer ) {

                    var l_values = [];
                    layer.values.forEach( function( r ) {
                        var title='', content='';
                        tooltip_def.title.forEach(function(title_key) {
                            if (''==title) {
                                title = r[title_key];
                            } else {
                                title += ' ' + r[title_key];
                            }
                        });
                        tooltip_def.content.forEach(function(infos) {
                            content += '<br/>' + infos.legend + ': ' + r[infos.key];
                        });
                        r.tooltip = '<strong>' + title + '</strong>' + content;
                        r.x = r[x_key]*1000;
                        //r.y = r[y1_key];
                        //r.y0 = 0;
                        /*l_values.push([{
                            x: r[x_key*1000],
                            y: r[y1_key],
                            tooltip: r.tooltip
                        }]);*/
                    });
                    return [l_values];
                });

            var layers = stack(rows);
        } else {
            // single layer
            var layers = d3.layout.stack()(
                [ y1_key ].map(
                    function( layer ) {
                        return rows.map(function(d) {
                            // transposition
                            return {
                                x: d[x_key],
                                y: +d[layer],
                                tooltip: d.tooltip
                            };
                        });
                    }
                )
            );
        }
    }
console.log('LAYERS', layers.length, layers);
    graph.color.domain([0, layers.length-1]);

    // Compute the x-domain and y-domain (by layer).
    // And update axis based on real data

    if ( !graph_def.pivot ) {
        graph.x.domain( rows.map( function(d) { return d[x_key]; }));
    } else {
        // pivoted graphs containing dates
        var xMax = d3.max(
            rows,
            function(row) {
                return d3.max( row.values, function(value) {
                    return value.x;
                });
            });
        var xMin = d3.min(
                rows,
                function(row) {
                    return d3.min( row.values, function(value) {
                        return value.x;
                    });
                });
        console.log('xMin', xMin, 'xMax', xMax);
        graph.x.domain([new Date(xMin), new Date(xMax)]);
    }

    graph.svg.selectAll("g.x-axis")
        .call(graph.xAxis)
            .selectAll("text")
            .style("text-anchor", "end")
            .style("font-size", "0.8em")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", function(d) {
                            return "rotate(-65)"
            });

    // Update the X Theme Grid
    if ( graph_def.pivot ) {
        console.log('todo theme x');
        /*graph.svg.selectAll('g.gridx')
            .call(d3.svg.axis().scale(graph.x).tickSize(-height).tickFormat(""));*/
    } else {
        graph.svg.selectAll('g.gridx')
            .call(d3.svg.axis().scale(graph.x).tickSize(-height).tickFormat(""));
    }

    // Update Left Axis
    var yLeftMax = d3.max(
        rows,
        function(row) {
            if ( graph_def.pivot ) {
                return d3.max( row.values, function(value) {
                    return value.y1;
                });
            } else {
                return row[y1_key];
            }
        }
    );
    yLeftMax = yLeftMax * 1.1;
    console.log('yLeftMax', yLeftMax);
    graph.yLeft.domain([0, yLeftMax]);
    graph.yAxisLeft.scale(graph.yLeft)
        /* enforce 10 ticks, seems the ticks() call, even forced on 2,5 or 10
         is not really following... */
        /*.ticks(10)*/
        .tickValues(d3.range(0, yLeftMax, Math.max(1,Math.floor((yLeftMax/9))) ));
    graph.svg.selectAll("g.axisLeft")
        .call(graph.yAxisLeft);

    if ( graph.def.graphic.has_y2 ) {
        // Update Right Axis
        var yRightMax = d3.max(
            rows,
            function(row) { return row[y2_key]; }
        );
        graph.yRight.domain([0, yRightMax]);
        graph.yAxisRight.scale(graph.yRight)
            /*.ticks(10); */
            .tickValues(d3.range(0, yRightMax, Math.max(1,Math.floor((yRightMax/9))) ));
        graph.svg.selectAll("g.axisRight")
            .call(graph.yAxisRight);
    }

    // Update the Y theme Grid
    graph.svg.selectAll('g.gridy')
            .call(d3.svg.axis().scale(graph.yLeft)
                    .tickSize(-width)
                    .tickValues(d3.range(0, yLeftMax, Math.max(1,Math.floor((yLeftMax/9))) ))
                    .orient("left")
                    .tickFormat(""));

    // Main data on graph
    if (graph_def.pivot) {

        // Add a group for each layer.
        var s_layers = graph.svg.selectAll("g.layer")
            .data(layers);

        // new layers
        s_layers.enter()
            .append("svg:g")
            .attr("class", "layer")
            .attr("transform", "translate(0,0)");

        // extra layers
        s_layers.exit()
            .remove();

        // Areas
        var paths = graph.svg.selectAll("path.data-area-path")
            .data(layers);

        var lines = graph.svg.selectAll("path.data-line-path")
            .data(layers);
           /* .data( function(d) { console.log("another d", d); return d.values; });*/

        console.log('paths', paths);

        var area = d3.svg.area()
         .x(function(d) { return graph.x(d.x); })
         .y0(function(d) { return graph.yLeft(d.y0); })
         .y1(function(d) { return graph.yLeft(d.y1); });

        var lineFunc = d3.svg.line()
            .x(function(d) { return graph.x(d.x); })
            .y(function(d) {
                return graph.yLeft(d.y);
            })
            .interpolate('linear');

        paths.enter().append('path')
            .attr("class", "data-area-path")
            .attr("d", function(d) { return area(d.values); })
            .style("fill", function(d,i) { return graph.color(i); })
            .style("opacity", 0.5)
            .append('title')
            .text(function(d) { return d.name; });

        paths.exit()
            .remove();

        paths.transition()
            .duration(1000)
            .delay(function(d, i) { return i * 10; })
            .attr("d", function(d) { return area(d.values); });

        lines.enter().append('path')
            .attr("class", "data-line-path")
            .attr("d", function(d) { return lineFunc(d.values); })
            .attr('stroke', function(d,i) { return graph.color(i); })
            .attr('stroke-width', 1)
            .attr('fill', 'none')
            .append('title')
            .text(function(d) { return d.name; });

        lines.exit()
        .remove();

        lines.transition()
            .duration(1000)
            .delay(function(d, i) { return i * 10; })
            .attr("d", function(d) { return lineFunc(d.values); });

        s_layers.each( function(d, i) {
            //var s_layer = d3.select(layer_elt);
            //console.log('each d', d, 'each elt', elt, 'each i', i);

            var s_layer = d3.select(this);
            console.log('each d', d, 'each elt', s_layer, 'each i', i);
            var dots = s_layer.selectAll("circle.data-circle")
                .data(d.values);

            dots.enter().append("circle")
                .attr("class", "data-circle")
                .attr("r", 3)
                .attr("cx", function(d) { return graph.x(d.x); })
                .attr("cy", function(d) { return height; })
                .style("fill", function(d,i) { return graph.color(i); })
                .on("mouseover", function(d) { toolTiping(d)});

            // remove extra dots
            dots.exit()
                .remove();

            dots.transition()
                .duration(1000)
                .delay(function(d, i) { return i * 10; })
                .attr("cx", function(d) { return graph.x(d.x); })
                .attr("cy", function(d) { return graph.yLeft(d.y); })
                .style("fill", function(d,i) { return graph.color(i); });
        });


    } else {


        // Add a group for each layer.
        var s_layers = graph.svg.selectAll("g.layer")
            .data(layers);

        // new layers
        s_layers.enter()
            .append("svg:g")
            .attr("class", "layer")
            .attr("transform", "translate(0,0)")
            .style("fill", function(d, i) {
                return color(i);
            })
            .style("stroke", function(d, i) {
                return d3.rgb(color(i)).darker();
            });
        // extra layers
        s_layers.exit()
            .remove();


        // Add a rect for each X.
        var rects = s_layers.selectAll("rect.data-rect")
            .data(function(d) { return d; });

        // new records
        rects.enter().append("svg:rect")
            .attr("class", "data-rect")
            .attr("x", function(d) { return graph.x(d.x); })
            .attr("width", 1)
            .attr("y", height)
            .attr("height",0)
            .on("mouseover", function(d) { toolTiping(d)});

        // extra records
        rects.exit()
          .transition()
            .duration(1000)
            .delay(function(d, i) { return i * 10; })
            .attr("x", function(d) { return width; })
            .attr("y",
              function(d) {
                return height; }
            )
            .attr("height",
              function(d) { return -height; }
            )
            .attr("width", graph.x.rangeBand())
            .remove();

        // Update old ones and also init the new ones
        rects.transition()
            .duration(1000)
            .delay(function(d, i) { return i * 10; })
            .attr("x", function(d) { return graph.x(d.x); })
            .attr("y",
              function(d) {
                return graph.yLeft(d.y0) -height + graph.yLeft(d.y); }
            )
            .attr("height",
              function(d) { return height - graph.yLeft(d.y); }
            )
            .attr("width", graph.x.rangeBand());
    }

    // Circle + line for Right Axis data
    if ( graph.def.graphic.has_y2 ) {
        var dots = graph.svg.selectAll("circle.data-circle")
            .data(rows);

        // append new dots
        dots.enter().append("circle")
            .attr("class", "data-circle")
            .attr("r", 4)
            .attr("cx", function(d) { return graph.x(d[x_key]) + graph.x.rangeBand()/2; })
            .attr("cy", function(d) { return height; })
            .style("fill", 'red')
            .on("mouseover", function(d) { toolTiping(d)});
        // remove extra dots
        dots.exit()
            .remove();

        var lineFunc = d3.svg.line()
            .x(function(d) { return graph.x(d[x_key])+ graph.x.rangeBand()/2; })
            .y(function(d) {
                return graph.yRight(d[y2_key]);
            })
            .interpolate('linear');

        // remove existing lines
        graph.svg.selectAll(".prod-graph-line").remove();

        // Update circles and launch the line at the end
        dots.transition()
            .duration(1000)
            .delay(function(d, i) { return i * 10; })
            .attr("cy", function(d) { return graph.yRight(d[y2_key]); })
            .attr("cx", function(d) { return graph.x(d[x_key]) + graph.x.rangeBand()/2; })
            .call(doneForAll, function() {
                // draw the line only after end of circle moves
                graph.svg.append('svg:path')
                    .attr('class','prod-graph-line')
                    .attr('d', lineFunc(rows))
                    .attr('stroke', 'red')
                    .attr('stroke-width', 1)
                    .attr('fill', 'none');
            });
    }

    manageTable( data, graph );
}

/**
 * Feed the HTML table with rows content
 */
function manageTable( data, graph ) {

    if ( graph.tablezone.empty() ) return false;

    var table_def = graph.def.table;
    var graph_def = graph.def.graphic;

    if (graph_def.pivot) {
        // RRD history graphs
        // case, rows are the different data sources, with several 'rows' of data
        // inside, as 'values' key.
        var rows = [];
        data.rows.forEach(function(source_record, i){
            source_record.values.forEach(function(record_row, i){
                record_row.time = new Date(parseInt(record_row.time,10) * 1000);
                rows.push(record_row);
            });
        });
    } else {
        // simple case, rows are table rows
        var rows = data.rows;
    }

    // rows of table
    var trs = graph.table.body.selectAll("tr")
        .data(rows);

    // new rows
    trs.enter()
        .append("tr")
            .attr("class", "data-tr");
    // removing extra rows
    trs.exit()
        .remove();

    // Cells for all theses rows
    var tds = trs.selectAll('td')
        .data(
            // map data to keep only part of the data that should
            // be in the table (filter by column)
            function( row ) {
                return table_def.columns.map(
                    function( column ) {
                        return {
                            column: column.key,
                            value: row[column.key],
                            style: column.style
                        };
                    });
            });

    // create new Tds
    tds.enter()
        .append('td');
    // remove old Tds
    tds.exit()
        .remove();
    // Tds update (will contain enter() created elements also)
    tds.text( function(d) { return d.value; } )
    .attr('style', function(d) { return d.style; } );

}

function loadSomeDefinitions( graph ) {
    var url = graph.def.graph_def_url + graph.def.graph_id;

    if (graph.placeholder.classed('prod-graph-rrd')) {
        url += '?level=' + graph.def.rrd_level;
    }
    d3.json( url
            , function(error, data) {

                if (null === error ) {
                    if (data.error) {
                        alert('error requesting json definition : ' + data.error_msg);
                        return false;
                    }
                }
                else {
                    alert('error while parsing json response');
                    return false;
                }

                // extends local definition
                for (var attrname in data.def) {
                    graph.def[attrname] = data.def[attrname];
                }
                console.log('NEW GRAPH', graph);

                // Finish graph init
                initGraph( graph );

                // Run ajax load and drawings for first time on this graph
                loadSomeData( graph );
            });

}

function loadSomeData( graph ) {

    var url = graph.def.graph_url + graph.def.graph_id + '?'

    if (! graph.filterszone.empty() ) {
        url += "rows=" + graph.filters.selector.property('value')
        + "&page="
        + (parseInt(graph.filters.pagecounter.property('value'),10))
        + "&sort="
        + graph.filters.sort.property('value');
    }
    d3.json(
        url
        , function(error, data) {

            if (null === error ) {
                if (data.error) {
                    alert('error requesting json data : ' + data.error_msg);
                    return false;
                }
                handleJsonData(data, graph);
            }
            else {
                alert('error while parsing json response');
                return false;
            }

        });

}


function starter_end( container, graph ) {

    graph.filterszone = container.select('div.prod-stat-graph-filters');
    graph.tablezone = container.select('div.prod-table-placeholder');

    graph.def = {
        graph_id : graph.placeholder.attr('id'),
        graph_def_url: graph.placeholder.attr('rel'),
    }

    if (graph.placeholder.classed('prod-graph-rrd')) {
        // extract rdd_level from id
        var full = graph.def.graph_id;
        graph.def.rrd_level = parseInt( full.substr( full.length -1 ), 10);
        graph.def.graph_id = full.substr( 0, full.length -2 );
    }

    // First ajax query, for full graph definition
    // chining will also launch first data query and graph creation
    loadSomeDefinitions( graph );
}

function starter( container ) {

    if ( container.empty() ) return false;

     // Init the Graphic definition
    var graph = {};
    var n_graph = {};

    // Read graph definition
    graph.imagezone = container.select('div.prod-stat-graph-image');
    if ( graph.imagezone.empty() ) return false;
    graph.placeholder = graph.imagezone.selectAll('div.prod-graph-placeholder');
    if ( graph.placeholder.empty() ) return false;

    if ( 1 === graph.placeholder[0].length ) {

        starter_end( container, graph );

    } else {
        // rrd multiple graphs
        graph.placeholder.each( function() {

            //n_graph = JSON.parse( JSON.stringify(n_graph) )
            n_graph = new Object();
            n_graph.imagezone = graph.imagezone
            n_graph.placeholder = d3.select(this);
            starter_end( container, n_graph );

        });
    }
}


//Find our place
jQuery('document').ready(function() {
    jQuery('div.prod-stat-result').once('d3-prod-report', function(){

        var $container = jQuery(this);
        var container = d3.select('#' + $container.attr('id'));

        if ( false === starter( container ) ) {

           alert('No graph found.');

        }

    });
});

</script>
<div class="prod-stats-results">
    <?php foreach ($results as $key => $item): ?>
    <section class="stat-result-section" id="<?php echo $key; ?>" >
      <div class="prod-stat-result" id="prod_stat_rs_<?php echo mt_rand(); ?>" >
        <h3>
          <?php echo render($item['title']); ?>
        </h3>
        <div class="prod-stat-graph">
        <?php if (! is_null($item['form'])): ?>
            <div class="prod-stat-graph-filters">
            <?php  echo render($item['form']); ?>
            </div>
        <?php endif ?>
        <div class="prod-stat-graph-image">
        <?php if (array_key_exists('graph_rrd', $item) && $item['graph_rrd']) : ?>
            <div class="prod-graph-placeholder prod-graph-rrd" id="<?php echo $item['graph_id']?>_1" rel="<?php echo $item['graph_url']?>"></div>
            <div class="prod-graph-placeholder prod-graph-rrd" id="<?php echo $item['graph_id']?>_2" rel="<?php echo $item['graph_url']?>"></div>
            <div class="prod-graph-placeholder prod-graph-rrd" id="<?php echo $item['graph_id']?>_3" rel="<?php echo $item['graph_url']?>"></div>
            <div class="prod-graph-placeholder prod-graph-rrd" id="<?php echo $item['graph_id']?>_4" rel="<?php echo $item['graph_url']?>"></div>
            <div class="prod-graph-placeholder prod-graph-rrd" id="<?php echo $item['graph_id']?>_5" rel="<?php echo $item['graph_url']?>"></div>
        <?php else :?>
            <div class="prod-graph-placeholder" id="<?php echo $item['graph_id']?>" rel="<?php echo $item['graph_url']?>"></span>
        <?php endif?>
        </div>
        </div>
        <div class="prod-stat-data">
          <fieldset class="collapsible collapsed"><legend><span class="fieldset-legend"><?php print t('Show datas table') ?></span></legend>
          <div class="fieldset-wrapper">
              <div class="prod-table-placeholder" id="<?php echo $item['graph_id']?>" rel="<?php echo $item['graph_url']?>"></div>
          </div>
          </fieldset>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
</div>
