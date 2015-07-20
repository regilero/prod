<div class="prod-stats-results">
    <?php foreach ($results as $key => $item): ?>
    <section class="stat-result-section" id="<?php echo $key; ?>" >
      <div class="prod-stat-result" >
        <h3>
          <?php echo render($item['title']); ?>
        </h3>
        <div class="prod-stat-graph">
        <form>
  <label><input type="radio" name="mode" value="grouped"> Grouped</label>
  <label><input type="radio" name="mode" value="stacked" checked> Stacked</label>
</form>
<style>

div.prod-d3tooltip {
  position: absolute;
  text-align: center;
  border: 0px;
  line-height: 1;
  font-weight: bold;
  padding: 12px;
  background: rgba(0, 0, 0, 0.8);
  color: #fff;
  border-radius: 5px;
}
</style>
<script type="text/javascript">

var n = 4, // number of layers
    m = 20, // number of samples per layer
    stack = d3.layout.stack();
/*
    var layers = stack(d3.range(n).map(function() { return bumpLayer(m, .1); }));
    var yGroupMax = d3.max(layers, function(layer) { return d3.max(layer, function(d) { return d.y; }); });
    var yStackMax = d3.max(layers, function(layer) { return d3.max(layer, function(d) { return d.y0 + d.y; }); });
*/


/*var y0 = d3.scale.linear().domain([300, 1100]).range([height, 0]),
y1 = d3.scale.linear().domain([20, 80]).range([height, 0]);
*/
var margin = {top: 40, right: 80, bottom: 100, left: 80},
    width = 800 - margin.left - margin.right,
    height = 600 - margin.top - margin.bottom;

var x = d3.scale.ordinal()
    /*.domain(d3.range(m))*/
    .rangeRoundBands([0, width], .1);
/*
var y = d3.scale.linear()
    .domain([300, 1100])
    .range([height, 0]);
*/

var xAxis = d3.svg.axis()
    .scale(x)
    .tickSize(1)
    /*.tickPadding(6)*/
    .orient("bottom");

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
/*
var xScale = d3.scale.log()
               .domain([1, Math.pow(2, 40)])
               .range([0, 480]);
*/
/*var xAxis = d3.svg.axis()
                .scale(xScale)
                .orient('left')
                .tickFormat(bytesToString)
                .tickValues(d3.range(11).map(
                    function (x) { return Math.pow(2, 4 * x); }
                ));
*/

d3.json("http://atelier-circonflexe.dev/admin/reports/prod/ajax/db_top", function(error, data) {
    console.log('error', error);
    console.log('data', data);

    var tooltip_def = data.meta.tooltip;
    var rows = data.rows;

    var color = d3.scale.linear()
        .domain([0, 2])
        .range(["#aad", "#556"]);

    // Transpose the data into layers for stacked layers
    var layers = d3.layout.stack()(
        ['size','idx_size'].map(
            function(layer) {
                return rows.map(function(d) {
                    // build title and content of tooltip
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
                    // transposition
                    return { 
                        x: d.table,
                        y: +d[layer],
                        tooltip: '<strong>' + title + '</strong>' + content
                    };
                });
            }
        )
    );
    console.log(layers);
    // Compute the x-domain (by table) and y-domain (by layer).
    x.domain( rows.map( function(d) { return d.table; }));
    /*//x.domain(layers[0].map(function(d) { return d.x; }));
    //y.domain([0, d3.max(layers[layers.length - 1], function(d) {
         return d.y0 + d.y; })]);
      
    //color.domain(d3.keys(['size','idx_size']));
*/
    var yMax = d3.max(
                   rows,
                   function(row) {
                       return row.full_size;
                   });

    var y = d3.scale.linear()
        .domain([0, yMax])
        .range([0, height]);
    
    /*y.domain(
        [ 0 , d3.max(
                layers,
                function(d) { return d.y0 + d+y; } 
            )
        ]
    ).range([0, height]);*/
    
    // create left & right yAxis
    var yAxisRight = d3.svg.axis()
        .scale(y)
        .tickSize(1)
        .ticks(6)
        .orient("right");
    var yAxisLeft = d3.svg.axis()
        .scale(y)
        .tickSize(1)
        .ticks(6)
        .tickFormat(bytesToString)
        .orient("left");

    // Tooltip info
    //Define 'div' for tooltips
    var tooltip_div = d3.select("body")
        .append("div") 
        .attr("class", "prod-d3tooltip")
        .style("opacity", 0);
    
    // Create the svg container
    var svg = d3.select("div.prod-stat-graph").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
      .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    
    // Add x Axis
    svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis)
            .selectAll("text")  
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", function(d) {
                return "rotate(-65)" 
            });
    
    // add right axis
    svg.append("g")
        .attr("class", "y axis axisRight")
        .attr("transform", "translate(" + (width) + ",0)")
        .call(yAxisRight)
        .append("text")
            .attr("y", 6)
            .attr("dy", "-2em")
            .attr("dx", "2em")
            .style("text-anchor", "end")
            .text("Nb Rows");
    
    // add left axis
    svg.append("g")
        .attr("class", "y axis axisLeft")
        .attr("transform", "translate(0,0)")
        .call(yAxisLeft)
    .append("text")
        .attr("y", 6)
        .attr("dy", "-2em")
        .style("text-anchor", "end")
        .text("Full Size");
    /*
    var layer = svg.selectAll(".layer")
        .data(layers)
    .enter().append("g")
        .attr("class", "layer")
        .style("fill", function(d, i) { return color(i); });
    */

    // Add a group for each layer.
    var s_layers = svg.selectAll("g.layer")
        .data(layers)
      .enter().append("svg:g")
        .attr("class", "layer")
        .attr("transform", "translate(0,0)")
        .style("fill", function(d, i) {
            return color(i);
        })
        .style("stroke", function(d, i) {
            return d3.rgb(color(i)).darker(); 
        });
    
    // Add a rect for each X.
    var rect = s_layers.selectAll("rect")
        .data(function(d) { return d; })
      .enter().append("svg:rect")
        .attr("x", function(d) { return x(d.x); })
        .attr("width", x.rangeBand())
        .attr("y", 
          function(d) {
            return ( height - ( y(d.y)) ) -y(d.y0); }
        )
        .attr("height",
          function(d) { return y(d.y); }
        )
        // Connect the tooltip
        .on("mouseover", function(d) {
            tooltip_div.transition()
                .duration(500)
                .style('opacity', 0);
            tooltip_div.html(d.tooltip);
            tooltip_height = tooltip_div.node().getBoundingClientRect().height;
            tooltip_div.style("left", (d3.event.pageX +15 ) + "px")
                .style("top", (d3.event.pageY - (tooltip_height+20)) + "px");;
            tooltip_div.transition()
                .duration(200)
                .style('opacity', .9);
        });
/*
    var rects = svg.selectAll(".rectfoo").data(rows).enter();
console.log("rects", rects);

    rects.append("rect")
        .attr("class", "rectfoo")
        .attr("x", function(d) { return x(d.table); })
        .attr("y", function(d,i,j) { 
            return y(d.full_size); }
        )
        .attr("width", x.rangeBand())
        .attr("height", function(d,i,j) { 
            return height - y(d.full_size); }
        );
*/
});








/*
rect.transition()
    .delay(function(d, i) { return i * 10; })
    .attr("y", function(d) { return y(d.y0 + d.y); })
    .attr("height", function(d) { return y(d.y0) - y(d.y0 + d.y); });
*/

/*
d3.selectAll("input").on("change", change);
*/
/*
var timeout = setTimeout(function() {
  d3.select("input[value=\"grouped\"]").property("checked", true).each(change);
}, 2000);
*/
/*
function change() {
  clearTimeout(timeout);
  if (this.value === "grouped") transitionGrouped();
  else transitionStacked();
}

function transitionGrouped() {
  y.domain([0, yGroupMax]);

  rect.transition()
      .duration(500)
      .delay(function(d, i) { return i * 10; })
      .attr("x", function(d, i, j) { return x(d.x) + x.rangeBand() / n * j; })
      .attr("width", x.rangeBand() / n)
    .transition()
      .attr("y", function(d) { return y(d.y); })
      .attr("height", function(d) { return height - y(d.y); });
}

function transitionStacked() {
  y.domain([0, yStackMax]);

  rect.transition()
      .duration(500)
      .delay(function(d, i) { return i * 10; })
      .attr("y", function(d) { return y(d.y0 + d.y); })
      .attr("height", function(d) { return y(d.y0) - y(d.y0 + d.y); })
    .transition()
      .attr("x", function(d) { return x(d.x); })
      .attr("width", x.rangeBand());
}
*/
/*
// Inspired by Lee Byron's test data generator.
function bumpLayer(n, o) {

  function bump(a) {
    var x = 1 / (.1 + Math.random()),
        y = 2 * Math.random() - .5,
        z = 10 / (.1 + Math.random());
    for (var i = 0; i < n; i++) {
      var w = (i / n - y) * z;
      a[i] += x * Math.exp(-w * w);
    }
  }

  var a = [], i;
  for (i = 0; i < n; ++i) a[i] = o + o * Math.random();
  for (i = 0; i < 5; ++i) bump(a);
  return a.map(function(d, i) { return {x: i, y: Math.max(0, d)}; });
}
*/

</script>

        </div>
        <div class="prod-stat-data">
          <fieldset class="collapsible collapsed"><legend><span class="fieldset-legend"><?php print t('Show datas table') ?></span></legend>
          <div class="fieldset-wrapper">
          <?php print render($item['table']); ?>
          </div>
          </fieldset>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
</div>
