/**
 * @layout false
 */
import React from "react";
import {Form, Button, Checkbox, Divider} from "antd";
import {Box, Heading, Image} from 'rebass';
import $ from 'miaoxing';
import logo from 'plugins/admin/images/logo.png';
import http from '@mxjs/http';
import {FormItem} from '@mxjs/a-form';
import {css, Global} from '@emotion/core';

export default class InstallIndex extends React.Component {
  state = {
    loading: false,
  }

  requestDefaultUrlRewrite = false;

  async componentDidMount() {
    const ret = await http.curPath('installed');
    if (ret.code !== 1) {
      $.alert(ret.message);
    }

    await this.checkUrlRewrite();
  }

  async checkUrlRewrite() {
    $.get({
      url: 'api/install/installed',
      ignoreError: true,
    }).then(ret => {
      if (ret && ret.code === 1) {
        this.requestDefaultUrlRewrite = true;
      }
    }).catch(() => {
      // Ignore error
    });
  }

  showAgreement = async (e) => {
    e.preventDefault();

    const ret = await http.curPath('license');
    if (ret.code !== 1) {
      $.ret(ret);
      return;
    }

    const index = ret.content.indexOf('\n');
    const title = ret.content.substr(0, index);
    const content = ret.content.substr(index + 1)
      .replace(/\n\n/g, "<br/><br/>")
      .replace(/\n/g, '');

    $.alert({
      title,
      content,
      html: true,
    });
  }

  handleSubmit = async (values) => {
    this.setState({loading: true});

    values.requestDefaultUrlRewrite = this.requestDefaultUrlRewrite;

    const ret = await http.curCreate({
      data: values,
      loading: true,
    });
    this.setState({loading: false});
    if (ret.code !== 1) {
      $.alert(ret.message);
      return;
    }

    await $.ret(ret);
    window.location = ret.next;
  }

  render() {
    return <>
      <Global
        styles={css`
          body {
            background: #f5f8fa url(https://image-10001577.image.myqcloud.com/uploads/3/20190602/15594729401485.jpg);
            background-size: cover;
          }
      `}
      />
      <Box
        width={700}
        mx="auto"
        mt={5}
        p={5}
        bg="white"
      >
        <Box
          mb={4}
          textAlign="center"
        >
          <Image height={30} src={logo}/>
        </Box>
        <Heading
          mb={5}
          textAlign="center"
          fontSize={3}
          fontWeight="normal"
          color="muted"
        >
          安装
        </Heading>
        <Form
          labelCol={{span: 8}}
          wrapperCol={{span: 8}}
          validateMessages={{
            required: '该项是必填的',
          }}
          initialValues={{
            dbHost: 'localhost',
            dbDbName: 'miaoxing',
            dbUser: 'root',
            dbTablePrefix: 'mx_',
            username: 'admin',
          }}
          onFinish={this.handleSubmit}
        >
          <FormItem label="数据库地址" name="dbHost" rules={[{required: true}]}
            extra="如果有端口号，使用`:`隔开"
          />
          <FormItem label="数据库名称" name="dbDbName" rules={[{required: true}]}/>
          <FormItem label="数据库用户名" name="dbUser" rules={[{required: true}]}/>
          <FormItem label="数据库密码" name="dbPassword" type="password" rules={[{required: true}]}/>
          <FormItem label="数据表前缀" name="dbTablePrefix" rules={[{required: true}]}/>

          <Divider/>

          <FormItem label="管理员用户名" name="username" rules={[{required: true}]}/>
          <FormItem label="管理员密码" name="password" type="password" rules={[{required: true}]}/>

          <Form.Item
            name="agree"
            wrapperCol={{offset: 8, span: 16}}
            valuePropName="checked"
            rules={[{required: true, message: "请阅读并同意《终端用户许可协议》"}]}
          >
            <Checkbox>我已阅读并同意<a href="#" onClick={this.showAgreement}>《终端用户许可协议》</a></Checkbox>
          </Form.Item>

          <Form.Item
            wrapperCol={{offset: 8, span: 8}}
          >
            <Button type="primary" htmlType="submit" block loading={this.state.loading}>
              安装
            </Button>
          </Form.Item>
        </Form>
      </Box>
    </>;
  }
}
